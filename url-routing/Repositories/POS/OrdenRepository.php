<?php

declare(strict_types=1);

namespace App\Repositories\POS;

use App\Repositories\Repository;

final class OrdenRepository extends Repository
{
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, usuario_id, estado, total_centavos, moneda, direccion_envio, numero_guia, creada_en
               FROM ordenes WHERE id = ?',
            [$id]
        );
    }

    /** @return list<array> with each line's subtotal */
    public function items(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT sku, producto_id, nombre, cantidad, precio_unitario_centavos,
                    cantidad * precio_unitario_centavos AS subtotal_centavos
               FROM orden_items WHERE orden_id = ? ORDER BY sku',
            [$id]
        );
    }

    /** @return list<array> shipment history, oldest first */
    public function eventos(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT ocurrido_en, estado, detalle FROM orden_eventos WHERE orden_id = ? ORDER BY ocurrido_en',
            [$id]
        );
    }

    /** @return list<array> newest first */
    public function deUsuario(int $usuarioId): array
    {
        return $this->db->fetchAll(
            'SELECT id, estado, total_centavos, moneda, creada_en
               FROM ordenes WHERE usuario_id = ? ORDER BY creada_en DESC, id DESC',
            [$usuarioId]
        );
    }
}
