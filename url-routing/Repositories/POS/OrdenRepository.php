<?php

declare(strict_types=1);

namespace App\Repositories\POS;

use App\Repositories\Repository;

final class OrdenRepository extends Repository
{
    /** Ways to retry an order that lost a race for its id or code against another one. */
    private const INTENTOS = 3;

    /** The states the table accepts (its CHECK, migration 001), in the order an order normally goes through. */
    public const ESTADOS = ['pendiente', 'pagada', 'enviada', 'entregada', 'cancelada', 'devuelta'];

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
     * @param array<string, int> $items
     * @return array{id: int, codigo: string}
     * @throws SinStock when a line can't be sold; nothing is changed then
     */
    public function crear(array $items, string $direccion, int $usuarioId): array
    {
        return $this->db->transaccionConReintento(fn(): array => $this->insertar($items, $direccion, $usuarioId), self::INTENTOS);
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

        $id = $this->db->siguienteNumero('ordenes', 'id');
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
     * Moves order $ordenId to $estado and anota the step in its history, in one transaction ($numeroGuia, if
     * given, is saved as the shipment's tracking number). Any state may follow any other: whoever runs the shop
     * knows what happened to the parcel, and a wrong step is fixed with another one. Nothing here puts the
     * stock back of an order that is cancelled or returned.
     *
     * @return bool false when there is no such order
     * @throws \InvalidArgumentException when $estado is not one of ESTADOS
     */
    public function cambiarEstado(int $ordenId, string $estado, ?string $detalle = null, ?string $numeroGuia = null): bool
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new \InvalidArgumentException("Estado desconocido: {$estado} (los válidos: " . implode(', ', self::ESTADOS) . ').');
        }

        return $this->db->transaccionConReintento(function () use ($ordenId, $estado, $detalle, $numeroGuia): bool {
            $guia = $numeroGuia === null ? '' : ', numero_guia = ?';
            $params = $numeroGuia === null ? [$estado, $ordenId] : [$estado, $numeroGuia, $ordenId];
            if ($this->db->execute("UPDATE ordenes SET estado = ?{$guia} WHERE id = ?", $params) !== 1) {
                return false;
            }
            $this->agregarEvento($ordenId, $estado, $detalle);

            return true;
        });
    }

    /**
     * Anota un paso de la orden con el número que le toca (1, 2, 3...): dos en el mismo segundo siguen
     * en el orden en que se anotaron, que la hora no distingue (ver la migración 003).
     */
    public function agregarEvento(int $ordenId, string $estado, ?string $detalle = null, ?string $cuando = null): void
    {
        $numero = $this->db->siguienteNumero('orden_eventos', 'orden', ['orden_id' => $ordenId]);
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
