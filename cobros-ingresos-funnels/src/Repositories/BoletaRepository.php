<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\EstadoBoleta;
use PDO;

final class BoletaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Alta manual de una boleta. Devuelve el id creado. */
    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO boletas (cliente_id, concepto, monto, moneda_codigo, fecha_emision, fecha_vencimiento)
             VALUES (:cliente_id, :concepto, :monto, :moneda_codigo, :fecha_emision, :fecha_vencimiento)
             RETURNING id'
        );
        $stmt->execute([
            ':cliente_id' => $datos['cliente_id'],
            ':concepto' => $datos['concepto'],
            ':monto' => $datos['monto'],
            ':moneda_codigo' => $datos['moneda_codigo'],
            ':fecha_emision' => $datos['fecha_emision'],
            ':fecha_vencimiento' => $datos['fecha_vencimiento'],
        ]);
        return (int) $stmt->fetchColumn();
    }

    /** Ingresos devengados (boletas emitidas), consolidados a USD, agrupados por mes de emision. */
    public function ingresosPorMes(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT to_char(b.fecha_emision, 'YYYY-MM') AS mes, SUM(b.monto * m.tasa_a_usd) AS total
             FROM boletas b
             JOIN monedas m ON m.codigo = b.moneda_codigo
             WHERE b.fecha_emision BETWEEN :desde AND :hasta
             GROUP BY mes ORDER BY mes"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    /** KPIs del periodo (consolidados a USD): boletas emitidas vs efectivo cobrado en el rango dado. */
    public function kpis(string $desde, string $hasta): array
    {
        $stmtFacturado = $this->db->prepare(
            "SELECT COALESCE(SUM(b.monto * m.tasa_a_usd), 0)
             FROM boletas b JOIN monedas m ON m.codigo = b.moneda_codigo
             WHERE b.fecha_emision BETWEEN :desde AND :hasta"
        );
        $stmtFacturado->execute([':desde' => $desde, ':hasta' => $hasta]);
        $facturado = (float) $stmtFacturado->fetchColumn();

        $stmtCobrado = $this->db->prepare(
            "SELECT COALESCE(SUM(p.monto * m.tasa_a_usd), 0)
             FROM pagos p JOIN monedas m ON m.codigo = p.moneda_codigo
             WHERE p.fecha_pago BETWEEN :desde AND :hasta"
        );
        $stmtCobrado->execute([':desde' => $desde, ':hasta' => $hasta]);
        $cobrado = (float) $stmtCobrado->fetchColumn();

        return [
            'facturado' => $facturado,
            'cobrado' => $cobrado,
            'tasa_cobranza' => $facturado > 0 ? $cobrado / $facturado : 0.0,
        ];
    }

    /** Saldo pendiente de la cartera (consolidado a USD), agrupado por antigüedad de vencimiento. */
    public function carteraAging(): array
    {
        $rows = $this->db->query(
            "SELECT b.id, b.fecha_vencimiento, m.tasa_a_usd,
                    b.monto - COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id), 0) AS saldo,
                    (CURRENT_DATE - b.fecha_vencimiento) AS dias_vencido
             FROM boletas b
             JOIN monedas m ON m.codigo = b.moneda_codigo"
        )->fetchAll();

        $buckets = [
            'Al dia' => 0.0,
            '1-30 dias' => 0.0,
            '31-60 dias' => 0.0,
            '61+ dias' => 0.0,
        ];

        foreach ($rows as $row) {
            $saldoUsd = (float) $row['saldo'] * (float) $row['tasa_a_usd'];
            if ($saldoUsd <= 0.01) {
                continue;
            }
            $dias = (int) $row['dias_vencido'];
            $bucket = match (true) {
                $dias < 0 => 'Al dia',
                $dias <= 30 => '1-30 dias',
                $dias <= 60 => '31-60 dias',
                default => '61+ dias',
            };
            $buckets[$bucket] += $saldoUsd;
        }

        return $buckets;
    }

    /** Todo el historial de boletas de un cliente puntual (para su ficha), sin filtro de fecha. */
    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.id, b.concepto, b.monto, b.moneda_codigo, b.fecha_emision, b.fecha_vencimiento,
                    COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id), 0) AS pagado
             FROM boletas b
             WHERE b.cliente_id = :id
             ORDER BY b.fecha_emision DESC"
        );
        $stmt->execute([':id' => $clienteId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $calculo = EstadoBoleta::calcular((float) $row['monto'], (float) $row['pagado'], $row['fecha_vencimiento']);
            $row['saldo'] = $calculo['saldo'];
            $row['estado'] = $calculo['estado'];
        }

        return $rows;
    }

    public function listado(string $desde, string $hasta, ?string $estado = null, ?string $cliente = null): array
    {
        $params = [':desde' => $desde, ':hasta' => $hasta];
        $filtroCliente = '';
        if ($cliente !== null && $cliente !== '') {
            $filtroCliente = ' AND c.nombre ILIKE :cliente';
            $params[':cliente'] = '%' . $cliente . '%';
        }

        $stmt = $this->db->prepare(
            "SELECT b.id, b.concepto, b.monto, b.moneda_codigo, b.fecha_emision, b.fecha_vencimiento,
                    c.id AS cliente_id, c.nombre AS cliente,
                    COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id), 0) AS pagado
             FROM boletas b
             JOIN clientes c ON c.id = b.cliente_id
             WHERE b.fecha_emision BETWEEN :desde AND :hasta{$filtroCliente}
             ORDER BY b.fecha_emision DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $resultado = [];
        foreach ($rows as $row) {
            $calculo = EstadoBoleta::calcular((float) $row['monto'], (float) $row['pagado'], $row['fecha_vencimiento']);

            if ($estado !== null && $estado !== '' && $calculo['estado'] !== $estado) {
                continue;
            }

            $row['saldo'] = $calculo['saldo'];
            $row['estado'] = $calculo['estado'];
            $resultado[] = $row;
        }

        return $resultado;
    }
}
