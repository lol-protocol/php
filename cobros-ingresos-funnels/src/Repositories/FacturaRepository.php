<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class FacturaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Ingresos devengados (facturacion) agrupados por mes de emision. */
    public function ingresosPorMes(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT strftime('%Y-%m', fecha_emision) AS mes, SUM(monto) AS total
             FROM facturas
             WHERE fecha_emision BETWEEN :desde AND :hasta
             GROUP BY mes ORDER BY mes"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    /** KPIs del periodo: ingresos facturados vs efectivo cobrado en el rango dado. */
    public function kpis(string $desde, string $hasta): array
    {
        $stmtFacturado = $this->db->prepare(
            'SELECT COALESCE(SUM(monto), 0) FROM facturas WHERE fecha_emision BETWEEN :desde AND :hasta'
        );
        $stmtFacturado->execute([':desde' => $desde, ':hasta' => $hasta]);
        $facturado = (float) $stmtFacturado->fetchColumn();

        $stmtCobrado = $this->db->prepare(
            'SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE fecha_pago BETWEEN :desde AND :hasta'
        );
        $stmtCobrado->execute([':desde' => $desde, ':hasta' => $hasta]);
        $cobrado = (float) $stmtCobrado->fetchColumn();

        return [
            'facturado' => $facturado,
            'cobrado' => $cobrado,
            'tasa_cobranza' => $facturado > 0 ? $cobrado / $facturado : 0.0,
        ];
    }

    /** Saldo pendiente de la cartera, agrupado por antigüedad de vencimiento. */
    public function carteraAging(): array
    {
        $rows = $this->db->query(
            "SELECT f.id, f.fecha_vencimiento,
                    f.monto - COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.factura_id = f.id), 0) AS saldo,
                    CAST(julianday('now') - julianday(f.fecha_vencimiento) AS INTEGER) AS dias_vencido
             FROM facturas f"
        )->fetchAll();

        $buckets = [
            'Al dia' => 0.0,
            '1-30 dias' => 0.0,
            '31-60 dias' => 0.0,
            '61+ dias' => 0.0,
        ];

        foreach ($rows as $row) {
            $saldo = (float) $row['saldo'];
            if ($saldo <= 0.01) {
                continue;
            }
            $dias = (int) $row['dias_vencido'];
            $bucket = match (true) {
                $dias < 0 => 'Al dia',
                $dias <= 30 => '1-30 dias',
                $dias <= 60 => '31-60 dias',
                default => '61+ dias',
            };
            $buckets[$bucket] += $saldo;
        }

        return $buckets;
    }

    public function listado(string $desde, string $hasta, ?string $estado = null): array
    {
        $stmt = $this->db->prepare(
            "SELECT f.id, f.concepto, f.monto, f.fecha_emision, f.fecha_vencimiento,
                    c.nombre AS cliente,
                    COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.factura_id = f.id), 0) AS pagado
             FROM facturas f
             JOIN clientes c ON c.id = f.cliente_id
             WHERE f.fecha_emision BETWEEN :desde AND :hasta
             ORDER BY f.fecha_emision DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        $rows = $stmt->fetchAll();

        $hoy = date('Y-m-d');
        $resultado = [];
        foreach ($rows as $row) {
            $saldo = round((float) $row['monto'] - (float) $row['pagado'], 2);
            if ($saldo <= 0.01) {
                $rowEstado = 'pagada';
            } elseif ($row['fecha_vencimiento'] < $hoy) {
                $rowEstado = 'vencida';
            } elseif ((float) $row['pagado'] > 0) {
                $rowEstado = 'parcial';
            } else {
                $rowEstado = 'pendiente';
            }

            if ($estado !== null && $estado !== '' && $rowEstado !== $estado) {
                continue;
            }

            $row['saldo'] = $saldo;
            $row['estado'] = $rowEstado;
            $resultado[] = $row;
        }

        return $resultado;
    }
}
