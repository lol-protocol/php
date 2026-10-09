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

    /**
     * Emite una nota de credito. Devuelve el id creado.
     *
     * Su tasa de cambio es el promedio, ponderado por monto, de las tasas de los
     * pagos vigentes de la boleta: la nota devuelve esos pagos, y asi cobro y
     * devolucion se cancelan en USD aunque la tasa se haya movido desde que se
     * cobro. Con la tasa de hoy, un pago de 100 a 0.80 devuelto a 0.85 dejaria un
     * "cobrado" negativo de 5 dolares que nadie cobro ni devolvio. Una boleta sin
     * pagos vigentes no da promedio (NULL) y la base graba la tasa del dia
     * (migracion 011).
     */
    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notas_credito (boleta_id, cliente_id, monto, moneda_codigo, fecha, motivo, tasa_a_usd)
             VALUES (:boleta_id, :cliente_id, :monto, :moneda_codigo, :fecha, :motivo,
                     (SELECT round(SUM(p.monto * p.tasa_a_usd) / SUM(p.monto), 12)
                      FROM pagos p
                      WHERE p.boleta_id = :boleta_id_pagos AND p.moneda_codigo = :moneda_pagos AND NOT p.anulada))
             RETURNING id'
        );
        $stmt->execute([
            ':boleta_id' => $datos['boleta_id'],
            ':cliente_id' => $datos['cliente_id'],
            ':monto' => $datos['monto'],
            ':moneda_codigo' => $datos['moneda_codigo'],
            ':fecha' => $datos['fecha'],
            ':motivo' => $datos['motivo'],
            ':boleta_id_pagos' => $datos['boleta_id'],
            ':moneda_pagos' => $datos['moneda_codigo'],
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

    /** Total devuelto en el rango, en USD a la tasa de cada nota (para netear los cobros del periodo). */
    public function totalEnRangoUsd(string $desde, string $hasta): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(n.monto * n.tasa_a_usd), 0)
             FROM notas_credito n
             WHERE n.fecha BETWEEN :desde AND :hasta'
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return (float) $stmt->fetchColumn();
    }

    /** Total devuelto por mes en el rango, en USD a la tasa de cada nota. @return array<string, float> mes => total */
    public function porMesUsd(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT to_char(n.fecha, 'YYYY-MM') AS mes, SUM(n.monto * n.tasa_a_usd) AS total
             FROM notas_credito n
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
