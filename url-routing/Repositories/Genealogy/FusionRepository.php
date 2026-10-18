<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

/**
 * Merges a duplicate persona into the one that stays, in one transaction:
 * everything that pointed at the duplicate (children, links, events,
 * organizations, colecciones, older aliases) moves to the one that stays,
 * the gaps of the one that stays (parents, surname group, viva mark) are
 * filled from the duplicate, the duplicate is deleted, and its id becomes an
 * alias (persona_alias), so its URLs redirect instead of breaking.
 */
final class FusionRepository extends Repository
{
    /** Join tables keyed by persona: table => [other key columns, other columns]. */
    private const RELACIONES = [
        'suceso_participantes' => [['suceso_id', 'rol'], []],
        'organizacion_miembros' => [['organizacion_id', 'rol'], ['desde', 'hasta']],
        'coleccion_personas' => [['coleccion_id'], []],
    ];

    /**
     * What a merge would move, without changing anything.
     *
     * @return array<string, int> what => how many rows
     */
    public function resumen(int $duplicado): array
    {
        $contar = fn(string $sql, int $veces = 1): int
            => (int)$this->db->fetchValue($sql, array_fill(0, $veces, $duplicado));

        $resumen = [
            'hijos' => $contar('SELECT COUNT(*) FROM personas WHERE padre_id = ? OR madre_id = ?', 2),
            'vinculos' => $contar('SELECT COUNT(*) FROM vinculos WHERE persona_a = ? OR persona_b = ?', 2),
        ];
        foreach (array_keys(self::RELACIONES) as $tabla) {
            $resumen[$tabla] = $contar("SELECT COUNT(*) FROM {$tabla} WHERE persona_id = ?");
        }
        $resumen['alias'] = $contar('SELECT COUNT(*) FROM persona_alias WHERE persona_id = ?');

        return $resumen;
    }

    /** @throws \DomainException when the two can't be the same person (see validar()) */
    public function fusionar(int $duplicado, int $vigente): void
    {
        $this->db->transaction(function () use ($duplicado, $vigente): void {
            $this->validar($duplicado, $vigente);

            // The one that stays keeps what it has and takes what it lacks.
            $this->db->execute(
                'UPDATE personas SET
                        padre_id = COALESCE(padre_id, (SELECT d.padre_id FROM personas d WHERE d.id = ?)),
                        madre_id = COALESCE(madre_id, (SELECT d.madre_id FROM personas d WHERE d.id = ?)),
                        grupo_id = COALESCE(grupo_id, (SELECT d.grupo_id FROM personas d WHERE d.id = ?)),
                        viva = COALESCE(viva, (SELECT d.viva FROM personas d WHERE d.id = ?))
                  WHERE id = ?',
                [$duplicado, $duplicado, $duplicado, $duplicado, $vigente]
            );
            $this->db->execute('UPDATE personas SET padre_id = ? WHERE padre_id = ?', [$vigente, $duplicado]);
            $this->db->execute('UPDATE personas SET madre_id = ? WHERE madre_id = ?', [$vigente, $duplicado]);

            // A link both had (spouse of the same person) is kept once; a symmetric
            // one counts in either order, (Juan, Elena) is (Elena, Juan).
            $this->db->execute(
                'INSERT INTO vinculos (persona_a, persona_b, tipo)
                 SELECT n.a, n.b, n.tipo FROM (
                        SELECT CASE WHEN persona_a = ? THEN CAST(? AS BIGINT) ELSE persona_a END AS a,
                               CASE WHEN persona_b = ? THEN CAST(? AS BIGINT) ELSE persona_b END AS b, tipo
                          FROM vinculos WHERE persona_a = ? OR persona_b = ?) n
                  WHERE NOT EXISTS (SELECT 1 FROM vinculos w WHERE w.tipo = n.tipo
                           AND ((w.persona_a = n.a AND w.persona_b = n.b)
                             OR (n.tipo IN (\'conyuge\', \'otro\') AND w.persona_a = n.b AND w.persona_b = n.a)))',
                [$duplicado, $vigente, $duplicado, $vigente, $duplicado, $duplicado]
            );
            $this->db->execute('DELETE FROM vinculos WHERE persona_a = ? OR persona_b = ?', [$duplicado, $duplicado]);

            foreach (self::RELACIONES as $tabla => [$clave, $otras]) {
                $this->mover($tabla, $clave, $otras, $duplicado, $vigente);
            }

            $this->db->execute('UPDATE persona_alias SET persona_id = ? WHERE persona_id = ?', [$vigente, $duplicado]);
            $this->db->execute('DELETE FROM personas WHERE id = ?', [$duplicado]);
            $this->db->execute('INSERT INTO persona_alias (id, persona_id) VALUES (?, ?)', [$duplicado, $vigente]);
        });
    }

    /** Refuses a merge that would leave the tree inconsistent. */
    private function validar(int $duplicado, int $vigente): void
    {
        if ($duplicado === $vigente) {
            throw new \DomainException('Una persona no se puede fusionar consigo misma.');
        }

        $filas = $this->db->fetchAll('SELECT id, padre_id, madre_id FROM personas WHERE id IN (?, ?)', [$duplicado, $vigente]);
        if (count($filas) !== 2) {
            throw new \DomainException('Las dos personas tienen que existir.');
        }

        foreach ($filas as $fila) {
            $otra = (int)$fila['id'] === $duplicado ? $vigente : $duplicado;
            if ((int)$fila['padre_id'] === $otra || (int)$fila['madre_id'] === $otra) {
                throw new \DomainException('Una es madre o padre de la otra: no pueden ser la misma persona.');
            }
        }

        $vinculadas = $this->db->fetchValue(
            'SELECT COUNT(*) FROM vinculos WHERE (persona_a = ? AND persona_b = ?) OR (persona_a = ? AND persona_b = ?)',
            [$duplicado, $vigente, $vigente, $duplicado]
        );
        if ((int)$vinculadas > 0) {
            throw new \DomainException('Tienen un vínculo entre sí: no pueden ser la misma persona.');
        }
    }

    /**
     * Moves $tabla's rows from $duplicado to $vigente; a row the one that stays
     * already has (same keys) is dropped instead of duplicated. Table and column
     * names come from RELACIONES, never from a request.
     *
     * @param list<string> $clave
     * @param list<string> $otras
     */
    private function mover(string $tabla, array $clave, array $otras, int $duplicado, int $vigente): void
    {
        $columnas = implode(', ', array_merge($clave, $otras));
        $mismaClave = implode(' AND ', array_map(static fn(string $c): string => "e.{$c} = d.{$c}", $clave));

        $this->db->execute(
            "INSERT INTO {$tabla} (persona_id, {$columnas})
             SELECT CAST(? AS BIGINT), {$columnas} FROM {$tabla} d
              WHERE d.persona_id = ?
                AND NOT EXISTS (SELECT 1 FROM {$tabla} e WHERE e.persona_id = ? AND {$mismaClave})",
            [$vigente, $duplicado, $vigente]
        );
        $this->db->execute("DELETE FROM {$tabla} WHERE persona_id = ?", [$duplicado]);
    }
}
