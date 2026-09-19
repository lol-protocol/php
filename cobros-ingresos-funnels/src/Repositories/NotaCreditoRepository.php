<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Notas de credito (devoluciones). Se emiten al anular una boleta que ya
 * tenia pagos: los pagos quedan intactos (la plata entro de verdad) y la
 * nota registra lo que hay que devolver. Los reportes de cobros restan
 * estas notas para mostrar el neto.
 */
final class NotaCreditoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Emite una nota de credito. Devuelve el id creado. */
    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notas_credito (boleta_id, cliente_id, monto, moneda_codigo, fecha, motivo)
             VALUES (:boleta_id, :cliente_id, :monto, :moneda_codigo, :fecha, :motivo)
             RETURNING id'
        );
        $stmt->execute([
            ':boleta_id' => $datos['boleta_id'],
            ':cliente_id' => $datos['cliente_id'],
            ':monto' => $datos['monto'],
            ':moneda_codigo' => $datos['moneda_codigo'],
            ':fecha' => $datos['fecha'],
            ':motivo' => $datos['motivo'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    /** Todas las notas de credito de un cliente (para su ficha), mas recientes primero. */
    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, boleta_id, monto, moneda_codigo, fecha, motivo
             FROM notas_credito WHERE cliente_id = :id
             ORDER BY fecha DESC, id DESC'
        );
        $stmt->execute([':id' => $clienteId]);
        return $stmt->fetchAll();
    }

    /** Total devuelto en el rango, consolidado a USD (para netear los cobros del periodo). */
    public function totalEnRangoUsd(string $desde, string $hasta): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(n.monto * m.tasa_a_usd), 0)
             FROM notas_credito n JOIN monedas m ON m.codigo = n.moneda_codigo
             WHERE n.fecha BETWEEN :desde AND :hasta'
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return (float) $stmt->fetchColumn();
    }

    /** Total devuelto por mes en el rango, consolidado a USD. @return array<string, float> mes => total */
    public function porMesUsd(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT to_char(n.fecha, 'YYYY-MM') AS mes, SUM(n.monto * m.tasa_a_usd) AS total
             FROM notas_credito n JOIN monedas m ON m.codigo = n.moneda_codigo
             WHERE n.fecha BETWEEN :desde AND :hasta
             GROUP BY mes"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);

        $porMes = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porMes[$fila['mes']] = (float) $fila['total'];
        }
        return $porMes;
    }
}
