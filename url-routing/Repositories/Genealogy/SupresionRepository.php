<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

/**
 * The owner's tool to answer a person who asks what the site holds about them (acceso) or to have it erased
 * (suprimir), run from bin/persona.php. It never serves a page. Hiding a persona (a pedido de retiro) leaves the
 * data in the database; this removes it:
 *
 *  - her events where she is the only participant go, with their links to records; the ones shared with
 *    other people stay (they are also the others' data) with her taken off;
 *  - her family links, her place in colecciones and organizaciones and the ids that used to redirect to her go;
 *  - her children keep their rows and lose the link to her as their padre or madre;
 *  - her row goes.
 *
 * What it cannot do is read free text: a title, a description or a note may still name her. menciones() finds
 * those rows for a person to read, and nothing is changed there.
 *
 * What stays is supresiones_persona: her id, when, the pedido that asked for it and a count of the rows removed
 * (no name), enough to prove it was done and to do it again after restoring an old backup (reaplicar()).
 */
final class SupresionRepository extends Repository
{
    /** Join tables keyed by persona_id whose rows go with her. */
    private const RELACIONES = ['organizacion_miembros', 'coleccion_personas', 'persona_alias'];

    /** How many ids menciones() lists per place; the count says if there are more. */
    private const MAX_MENCIONES = 25;

    /**
     * Everything the site holds about a persona, for her to read. Relatives appear as counts and nothing
     * else: their names are their own data.
     *
     * @return array<string, mixed>|null null when there is no such persona
     */
    public function acceso(int $id): ?array
    {
        $persona = $this->db->fetchOne('SELECT * FROM personas WHERE id = ?', [$id]);
        if ($persona === null) {
            return null;
        }

        $familiares = [
            'padre' => $persona['padre_id'] !== null,
            'madre' => $persona['madre_id'] !== null,
            'hijos' => (int)$this->db->fetchValue('SELECT COUNT(*) FROM personas WHERE padre_id = ? OR madre_id = ?', [$id, $id]),
            'otros_vinculos' => (int)$this->db->fetchValue('SELECT COUNT(*) FROM vinculos WHERE persona_a = ? OR persona_b = ?', [$id, $id]),
        ];
        unset($persona['padre_id'], $persona['madre_id']);

        return [
            'persona' => $persona,
            'familiares' => $familiares,
            'sucesos' => $this->db->fetchAll(
                'SELECT s.id, s.tipo, s.fecha, s.lugar_ruta, s.descripcion, sp.rol
                   FROM suceso_participantes sp JOIN sucesos s ON s.id = sp.suceso_id
                  WHERE sp.persona_id = ? ORDER BY s.fecha, s.id',
                [$id]
            ),
            'registros' => $this->db->fetchAll(
                'SELECT DISTINCT r.id, r.titulo, r.tipo, r.fecha, r.fuente
                   FROM registros r
                   JOIN registro_sucesos rs ON rs.registro_id = r.id
                   JOIN suceso_participantes sp ON sp.suceso_id = rs.suceso_id
                  WHERE sp.persona_id = ? ORDER BY r.fecha, r.id',
                [$id]
            ),
            'colecciones' => array_map(
                static fn(array $c): array => ['id' => (int)$c['id'], 'nombre' => $c['nombre'], 'publica' => (bool)$c['publica']],
                $this->db->fetchAll(
                    'SELECT c.id, c.nombre, c.publica FROM coleccion_personas cp JOIN colecciones c ON c.id = cp.coleccion_id
                      WHERE cp.persona_id = ? ORDER BY c.id',
                    [$id]
                )
            ),
            'organizaciones' => $this->db->fetchAll(
                'SELECT o.id, o.nombre, m.rol, m.desde, m.hasta
                   FROM organizacion_miembros m JOIN organizaciones o ON o.id = m.organizacion_id
                  WHERE m.persona_id = ? ORDER BY o.id, m.rol',
                [$id]
            ),
            'ids_que_redirigen_a_ella' => array_map(
                'intval',
                array_column($this->db->fetchAll('SELECT id FROM persona_alias WHERE persona_id = ? ORDER BY id', [$id]), 'id')
            ),
            // Not who asked nor what they wrote: that is the requester's data, not hers.
            'pedidos_de_retiro' => $this->db->fetchAll(
                "SELECT id, motivo, estado, creado_en FROM pedidos_retiro WHERE tipo = 'persona' AND recurso_id = ? ORDER BY id",
                [$id]
            ),
        ];
    }

    /**
     * What suprimir() would remove, without changing anything.
     *
     * @return array<string, int> what => how many rows
     */
    public function resumen(int $id): array
    {
        $propios = $this->sucesosSoloDeEllas($id);
        $total = (int)$this->db->fetchValue('SELECT COUNT(*) FROM suceso_participantes WHERE persona_id = ?', [$id]);

        $registros = 0;
        foreach ($propios as $suceso) {
            $registros += (int)$this->db->fetchValue('SELECT COUNT(*) FROM registro_sucesos WHERE suceso_id = ?', [$suceso]);
        }

        $resumen = [
            'sucesos_que_se_borran' => count($propios),
            'sucesos_compartidos_que_se_conservan' => $total - count($propios),
            'enlaces_a_registros_que_se_quitan' => $registros,
            'vinculos' => (int)$this->db->fetchValue('SELECT COUNT(*) FROM vinculos WHERE persona_a = ? OR persona_b = ?', [$id, $id]),
            'hijos_que_pierden_el_enlace' => (int)$this->db->fetchValue('SELECT COUNT(*) FROM personas WHERE padre_id = ? OR madre_id = ?', [$id, $id]),
        ];
        foreach (self::RELACIONES as $tabla) {
            $resumen[$tabla] = (int)$this->db->fetchValue("SELECT COUNT(*) FROM {$tabla} WHERE persona_id = ?", [$id]);
        }

        return $resumen;
    }

    /**
     * Removes a persona (see the class comment), in one transaction, and records it.
     *
     * @param int|null $pedido the pedido de retiro that asked for it, for the record
     * @return array<string, int> what was removed (see resumen())
     * @throws \DomainException when there is no such persona or no such pedido
     */
    public function suprimir(int $id, ?int $pedido = null): array
    {
        return $this->db->transaction(function () use ($id, $pedido): array {
            if ($this->db->fetchValue('SELECT 1 FROM personas WHERE id = ?', [$id]) === null) {
                throw new \DomainException("No existe la persona {$id}.");
            }
            if ($pedido !== null) {
                $fila = $this->db->fetchOne('SELECT tipo, recurso_id FROM pedidos_retiro WHERE id = ?', [$pedido]);
                if ($fila === null) {
                    throw new \DomainException("No existe el pedido de retiro {$pedido}.");
                }
                if ($fila['tipo'] !== 'persona' || (int)$fila['recurso_id'] !== $id) {
                    throw new \DomainException("El pedido de retiro {$pedido} no es sobre la persona {$id}.");
                }
            }

            $resumen = $this->borrar($id);
            $this->db->insert('supresiones_persona', [
                'id' => $id,
                'suprimida_en' => date('Y-m-d H:i:s'),
                'pedido_id' => $pedido,
                'resumen' => json_encode($resumen, JSON_THROW_ON_ERROR),
            ]);

            return $resumen;
        });
    }

    /**
     * Personas that are in the database although they were erased: what is left after restoring an old backup.
     *
     * @return list<int>
     */
    public function volvieron(): array
    {
        $filas = $this->db->fetchAll('SELECT s.id FROM supresiones_persona s JOIN personas p ON p.id = s.id ORDER BY s.id');

        return array_map('intval', array_column($filas, 'id'));
    }

    /**
     * Removes again every persona volvieron() lists; the record of her erasure is already there.
     *
     * @return list<int> the ids it removed
     */
    public function reaplicar(): array
    {
        $ids = $this->volvieron();
        foreach ($ids as $id) {
            $this->db->transaction(function () use ($id): void {
                $this->borrar($id);
            });
        }

        return $ids;
    }

    /**
     * Rows of free text that contain a name, to read by hand: a description or a title can name a person without
     * being linked to her. Looks for the whole name, for all the given names with the first surname and for the
     * first given name with the first surname, ignoring case and accents. It is a net with holes (a nickname, a
     * "Quintana Soto, Rosa", a misspelling), so it narrows what a person reads, it does not replace reading.
     * Nothing is changed.
     *
     * @return array<string, array{total: int, ids: list<int>}> "table.column" => where it appears
     */
    public function menciones(string $nombres, string $apellidos): array
    {
        $primerNombre = explode(' ', trim($nombres))[0];
        $primerApellido = explode(' ', trim($apellidos))[0];
        $patrones = array_values(array_unique(array_filter([
            self::patron(trim("{$nombres} {$apellidos}")),
            self::patron(trim("{$nombres} {$primerApellido}")),
            self::patron(trim("{$primerNombre} {$primerApellido}")),
        ], static fn(string $p): bool => mb_strlen($p) >= 7)));
        if ($patrones === []) {
            return [];
        }

        $lugares = [
            ['sucesos', 'descripcion', ''],
            ['registros', 'titulo', ''],
            ['registros', 'fuente', ''],
            ['colecciones', 'nombre', ''],
            ['colecciones', 'descripcion', ''],
            ['grupos', 'descripcion', ''],
            ['pedidos_retiro', 'detalle', ' AND depurado_en IS NULL'],
        ];
        $hallazgos = [];
        foreach ($lugares as [$tabla, $columna, $extra]) {
            $donde = implode(' OR ', array_fill(0, count($patrones), self::sinAcentos("COALESCE({$columna}, '')") . self::LIKE_ESCAPED));
            $filas = $this->db->fetchAll("SELECT id FROM {$tabla} WHERE ({$donde}){$extra} ORDER BY id", $patrones);
            if ($filas !== []) {
                $hallazgos["{$tabla}.{$columna}"] = [
                    'total' => count($filas),
                    'ids' => array_map('intval', array_slice(array_column($filas, 'id'), 0, self::MAX_MENCIONES)),
                ];
            }
        }

        return $hallazgos;
    }

    /**
     * The deletes themselves, inside the caller's transaction. Order matters: nothing may still point at a row when
     * it goes (foreign keys), so events and links go before the persona.
     *
     * @return array<string, int>
     */
    private function borrar(int $id): array
    {
        $resumen = $this->resumen($id);
        $propios = $this->sucesosSoloDeEllas($id);

        $this->db->execute('DELETE FROM suceso_participantes WHERE persona_id = ?', [$id]);
        foreach ($propios as $suceso) {
            $this->db->execute('DELETE FROM registro_sucesos WHERE suceso_id = ?', [$suceso]);
            $this->db->execute('DELETE FROM sucesos WHERE id = ?', [$suceso]);
        }
        $this->db->execute('DELETE FROM vinculos WHERE persona_a = ? OR persona_b = ?', [$id, $id]);
        foreach (self::RELACIONES as $tabla) {
            $this->db->execute("DELETE FROM {$tabla} WHERE persona_id = ?", [$id]);
        }
        $this->db->execute('UPDATE personas SET padre_id = NULL WHERE padre_id = ?', [$id]);
        $this->db->execute('UPDATE personas SET madre_id = NULL WHERE madre_id = ?', [$id]);
        $this->db->execute('DELETE FROM personas WHERE id = ?', [$id]);

        return $resumen;
    }

    /** @return list<int> events whose only participant is the persona */
    private function sucesosSoloDeEllas(int $id): array
    {
        $filas = $this->db->fetchAll(
            'SELECT DISTINCT sp.suceso_id AS id FROM suceso_participantes sp
              WHERE sp.persona_id = ?
                AND NOT EXISTS (SELECT 1 FROM suceso_participantes o WHERE o.suceso_id = sp.suceso_id AND o.persona_id <> ?)
              ORDER BY sp.suceso_id',
            [$id, $id]
        );

        return array_map('intval', array_column($filas, 'id'));
    }
}
