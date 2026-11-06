<?php

declare(strict_types=1);

namespace App\Repositories\POS;

use App\Repositories\Repository;

final class OrdenRepository extends Repository
{
    /** Ways to retry an order that lost a race for its id or code against another one. */
    private const INTENTOS = 3;

    private const COLUMNAS = 'id, codigo, usuario_id, estado, total_centavos, moneda, direccion_envio, numero_guia, creada_en';

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT ' . self::COLUMNAS . ' FROM ordenes WHERE id = ?', [$id]);
    }

    /**
     * By the public code in its URL (see migration 002_ordenes_codigo).
     *
     * @return array<string, mixed>|null
     */
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
     * Only that clash is tried again (see choqueDeIdOCodigo()): a CHECK that refuses the data, a missing
     * user or a database that is down would fail the same way every time. SQLite reports every constraint
     * (23000) under one code, so the code alone can't tell them apart.
     *
     * @param array<string, int> $items
     * @return array{id: int, codigo: string}
     * @throws \InvalidArgumentException when there are no lines or one asks for fewer than one unit
     * @throws SinStock when a line can't be sold; nothing is changed then
     */
    public function crear(array $items, string $direccion, int $usuarioId): array
    {
        if ($items === []) {
            throw new \InvalidArgumentException('Una orden necesita al menos una línea.');
        }
        foreach ($items as $sku => $cantidad) {
            if ($cantidad < 1) {
                throw new \InvalidArgumentException("Cantidad inválida para {$sku}: {$cantidad}");
            }
        }

        for ($intento = 1; ; $intento++) {
            try {
                return $this->db->transaction(fn(): array => $this->insertar($items, $direccion, $usuarioId));
            } catch (\PDOException $e) {
                if ($intento >= self::INTENTOS || !self::choqueDeIdOCodigo($e)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Whether the database refused the INSERT of the order because its id or its code already exists
     * (PostgreSQL and SQLite word it differently; the message names the table, `ordenes`). The same words
     * about orden_items or orden_eventos are not this clash and retrying would not fix them.
     */
    private static function choqueDeIdOCodigo(\PDOException $e): bool
    {
        $mensaje = strtolower($e->getMessage());

        return (str_contains($mensaje, 'duplicate key') || str_contains($mensaje, 'unique constraint failed'))
            && str_contains($mensaje, 'ordenes');
    }

    /**
     * @return array{id: int, codigo: string}
     * @param array<int|string, int> $items
     */
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
        $this->agregarEvento($id, 'pendiente', 'Orden recibida.');

        return ['id' => $id, 'codigo' => (string)$codigo];
    }

    /**
     * Anota un paso de la orden con el número que le toca (1, 2, 3...): dos en el mismo segundo siguen
     * en el orden en que se anotaron, que la hora no distingue (ver la migración 003).
     *
     * El número es MAX(orden) + 1, como el id de la orden: dos llamadas a la vez para la misma orden leerían
     * el mismo máximo y la segunda chocaría con la clave (orden_id, orden). Hoy solo la llama crear(), dentro
     * de su transacción y para una orden recién creada que nadie más conoce, así que no puede pasar; el día
     * que algo más anote pasos (confirmar un pago, una guía de envío), esa llamada tiene que reintentar el
     * choque como lo hace crear() con el id y el código.
     */
    public function agregarEvento(int $ordenId, string $estado, ?string $detalle = null, ?string $cuando = null): void
    {
        $numero = (int)$this->db->fetchValue('SELECT COALESCE(MAX(orden), 0) + 1 FROM orden_eventos WHERE orden_id = ?', [$ordenId]);
        $this->db->execute(
            'INSERT INTO orden_eventos (orden_id, orden, ocurrido_en, estado, detalle) VALUES (?, ?, ?, ?, ?)',
            [$ordenId, $numero, $cuando ?? date('Y-m-d H:i:s'), $estado, $detalle]
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
            'SELECT ocurrido_en, estado, detalle FROM orden_eventos WHERE orden_id = ? ORDER BY orden',
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
