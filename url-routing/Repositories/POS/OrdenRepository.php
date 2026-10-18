<?php

declare(strict_types=1);

namespace App\Repositories\POS;

use App\Repositories\Repository;

final class OrdenRepository extends Repository
{
    private const COLUMNAS = 'id, codigo, usuario_id, estado, total_centavos, moneda, direccion_envio, numero_guia, creada_en';

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT ' . self::COLUMNAS . ' FROM ordenes WHERE id = ?', [$id]);
    }

    /** By the public code in its URL (see migration 002_ordenes_codigo). */
    public function porCodigo(int $codigo): ?array
    {
        return $this->db->fetchOne('SELECT ' . self::COLUMNAS . ' FROM ordenes WHERE codigo = ?', [$codigo]);
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
            'SELECT id, codigo, estado, total_centavos, moneda, creada_en
               FROM ordenes WHERE usuario_id = ? ORDER BY creada_en DESC, id DESC',
            [$usuarioId]
        );
    }
}
