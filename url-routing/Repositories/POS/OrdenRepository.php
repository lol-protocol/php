<?php

declare(strict_types=1);

namespace App\Repositories\POS;

use App\Repositories\Repository;

final class OrdenRepository extends Repository
{
    /** Ways to retry an order that lost a race for its id or code against another one. */
    private const INTENTOS = 3;

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

    /**
     * Places an order for $items (sku => cantidad), in one transaction: each
     * line's stock goes down only if there is still enough (so two buyers can't
     * both take the last unit), and the order copies each line's name and
     * price as they are now. Its URL code is random (random_int), never derived
     * from its id. A clash with another order being placed at the same moment
     * (same id or code) is retried.
     *
     * @param array<string, int> $items
     * @return array{id: int, codigo: string}
     * @throws SinStock when a line can't be sold; nothing is changed then
     */
    public function crear(array $items, string $direccion, int $usuarioId): array
    {
        for ($intento = 1; ; $intento++) {
            try {
                return $this->db->transaction(fn(): array => $this->insertar($items, $direccion, $usuarioId));
            } catch (\PDOException $e) {
                if ($intento >= self::INTENTOS || !in_array((string)$e->getCode(), ['23000', '23505'], true)) {
                    throw $e;
                }
            }
        }
    }

    /** @return array{id: int, codigo: string} */
    private function insertar(array $items, string $direccion, int $usuarioId): array
    {
        $variantes = (new ProductoRepository($this->db))->variantesPorSkus(array_map('strval', array_keys($items)));
        $lineas = [];
        $total = 0;
        $moneda = null;

        foreach ($items as $sku => $cantidad) {
            $sku = (string)$sku;
            $variante = $variantes[$sku] ?? null;
            $nombre = $variante === null ? $sku : $variante['producto_nombre'] . ' — ' . $variante['variante_nombre'];
            $vendida = $variante !== null && $variante['activo'] && $this->db->execute(
                'UPDATE variantes SET stock = stock - ? WHERE sku = ? AND stock >= ?',
                [$cantidad, $sku, $cantidad]
            ) === 1;
            if (!$vendida) {
                throw new SinStock($nombre);
            }
            if ($moneda !== null && $variante['moneda'] !== $moneda) {
                throw new \DomainException('Una orden no puede mezclar monedas.');
            }

            $moneda = $variante['moneda'];
            $total += $variante['precio_centavos'] * $cantidad;
            $lineas[] = [$sku, $variante['producto_id'], $nombre, $cantidad, $variante['precio_centavos']];
        }

        $id = (int)$this->db->fetchValue('SELECT COALESCE(MAX(id), 0) + 1 FROM ordenes');
        $codigo = random_int(1000000000000000, 9999999999999999);
        $this->db->execute(
            "INSERT INTO ordenes (id, codigo, usuario_id, estado, total_centavos, moneda, direccion_envio)
             VALUES (?, ?, ?, 'pendiente', ?, ?, ?)",
            [$id, $codigo, $usuarioId, $total, $moneda, $direccion]
        );
        foreach ($lineas as [$sku, $productoId, $nombre, $cantidad, $precio]) {
            $this->db->execute(
                'INSERT INTO orden_items (orden_id, sku, producto_id, nombre, cantidad, precio_unitario_centavos) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $sku, $productoId, $nombre, $cantidad, $precio]
            );
        }
        $this->db->execute(
            "INSERT INTO orden_eventos (orden_id, ocurrido_en, estado, detalle) VALUES (?, ?, 'pendiente', 'Orden recibida.')",
            [$id, date('Y-m-d H:i:s')]
        );

        return ['id' => $id, 'codigo' => (string)$codigo];
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
