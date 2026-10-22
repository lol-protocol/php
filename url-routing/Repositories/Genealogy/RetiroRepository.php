<?php

declare(strict_types=1);

namespace App\Repositories\Genealogy;

use App\Repositories\Repository;

/**
 * Pedidos de retiro (/r/{id}/) and what the owner decided about each one (/0/3/). A page with a
 * pedido 'oculto' or 'retirado' is withheld from visitors (Support/Retiros.php) and left out of the
 * sitemap. Every change of state is appended to pedido_retiro_eventos, which is never updated: it is
 * the record to answer a complaint with.
 */
final class RetiroRepository extends Repository
{
    /** Each type that can be reported, and the table its id lives in. */
    public const TABLAS = [
        'persona' => 'personas',
        'suceso' => 'sucesos',
        'registro' => 'registros',
        'coleccion' => 'colecciones',
        'grupo' => 'grupos',
        'organizacion' => 'organizaciones',
    ];

    public const MOTIVOS = [
        'me_nombra' => 'Habla de mí',
        'familiar' => 'Habla de un familiar mío fallecido',
        'terminos' => 'Infringe los términos de uso',
        'otro' => 'Otro motivo',
    ];

    /** What the owner may decide, and the state each decision leaves the pedido in. */
    public const DECISIONES = ['ocultar' => 'oculto', 'retirar' => 'retirado', 'rechazar' => 'rechazado'];

    /** The states that withhold the page from visitors. */
    private const QUE_OCULTAN = "('oculto', 'retirado')";

    /** SQL: true when the $tipo row whose id is $columna is withheld (for WHERE clauses; $tipo is a TABLAS key). */
    public static function sqlRetirado(string $tipo, string $columna): string
    {
        return "EXISTS (SELECT 1 FROM pedidos_retiro rr WHERE rr.tipo = '{$tipo}' AND rr.recurso_id = {$columna}"
            . ' AND rr.estado IN ' . self::QUE_OCULTAN . ')';
    }

    /**
     * Whether a visitor would reach the page if it had no pedido de retiro, as the rest of the site decides
     * it (Privacidad): an event or a record that would give a living person away and a private colección
     * are not there for a visitor, so they can't be reported, and a pedido on one doesn't turn its 404 into
     * a 410 that confirms it exists. A living persona's page is there (it shows only «Persona viva»).
     */
    public function existe(string $tipo, int $id): bool
    {
        $desde = match ($tipo) {
            'suceso' => 'sucesos x WHERE ' . $this->privacidad->sqlSucesoVisible('x'),
            'registro' => 'registros x WHERE ' . $this->privacidad->sqlRegistroVisible('x'),
            'coleccion' => 'colecciones x WHERE x.publica',
            default => isset(self::TABLAS[$tipo]) ? self::TABLAS[$tipo] . ' x WHERE TRUE' : null,
        };

        return $desde !== null && $this->db->fetchValue("SELECT 1 FROM {$desde} AND x.id = ?", [$id]) !== null;
    }

    public function retirado(string $tipo, int $id): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM pedidos_retiro WHERE tipo = ? AND recurso_id = ? AND estado IN ' . self::QUE_OCULTAN,
            [$tipo, $id]
        ) !== null;
    }

    /** Stores a new pedido ('pendiente', with its 'recibido' event) and returns its number. */
    public function crear(string $tipo, int $id, string $motivo, string $detalle, string $contacto): int
    {
        return $this->db->transaction(function () use ($tipo, $id, $motivo, $detalle, $contacto): int {
            $numero = (int)$this->db->fetchValue('SELECT COALESCE(MAX(id), 0) + 1 FROM pedidos_retiro');
            $ahora = date('Y-m-d H:i:s');
            $this->db->execute(
                'INSERT INTO pedidos_retiro (id, tipo, recurso_id, motivo, detalle, contacto, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$numero, $tipo, $id, $motivo, $detalle, $contacto, $ahora]
            );
            $this->evento($numero, 'recibido', null, $ahora);

            return $numero;
        });
    }

    /** The owner's decision ($decision a DECISIONES key) on pedido $numero; false when there is no such pedido. */
    public function decidir(int $numero, string $decision, ?string $nota): bool
    {
        $estado = self::DECISIONES[$decision];

        return $this->db->transaction(function () use ($numero, $estado, $nota): bool {
            if ($this->db->execute('UPDATE pedidos_retiro SET estado = ? WHERE id = ?', [$estado, $numero]) !== 1) {
                return false;
            }
            $this->evento($numero, $estado, $nota, date('Y-m-d H:i:s'));

            return true;
        });
    }

    /** @return list<array> every pedido, the pending ones first, then the newest, each with its 'eventos' */
    public function todos(): array
    {
        $pedidos = $this->db->fetchAll(
            "SELECT id, tipo, recurso_id, motivo, detalle, contacto, estado, creado_en FROM pedidos_retiro
              ORDER BY CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END, creado_en DESC, id DESC"
        );
        foreach ($pedidos as &$pedido) {
            $pedido['eventos'] = $this->db->fetchAll(
                'SELECT ocurrido_en, estado, nota FROM pedido_retiro_eventos WHERE pedido_id = ? ORDER BY orden',
                [$pedido['id']]
            );
        }

        return $pedidos;
    }

    private function evento(int $numero, string $estado, ?string $nota, string $cuando): void
    {
        $orden = (int)$this->db->fetchValue('SELECT COALESCE(MAX(orden), 0) + 1 FROM pedido_retiro_eventos WHERE pedido_id = ?', [$numero]);
        $this->db->execute(
            'INSERT INTO pedido_retiro_eventos (pedido_id, orden, ocurrido_en, estado, nota) VALUES (?, ?, ?, ?, ?)',
            [$numero, $orden, $cuando, $estado, $nota]
        );
    }
}
