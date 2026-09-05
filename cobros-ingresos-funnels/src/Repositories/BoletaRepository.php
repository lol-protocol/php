<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\EstadoBoleta;
use App\Paginacion;
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

    public function porId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT b.*, c.nombre AS cliente
             FROM boletas b JOIN clientes c ON c.id = b.cliente_id
             WHERE b.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Edita concepto/monto/fechas. No se puede reasignar el cliente ni la moneda. */
    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            'UPDATE boletas SET concepto = :concepto, monto = :monto,
                fecha_emision = :fecha_emision, fecha_vencimiento = :fecha_vencimiento
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':concepto' => $datos['concepto'],
            ':monto' => $datos['monto'],
            ':fecha_emision' => $datos['fecha_emision'],
            ':fecha_vencimiento' => $datos['fecha_vencimiento'],
        ]);
    }

    public function anular(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE boletas SET anulada = TRUE WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** Ingresos devengados (boletas emitidas, sin anular), consolidados a USD, agrupados por mes de emision. */
    public function ingresosPorMes(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT to_char(b.fecha_emision, 'YYYY-MM') AS mes, SUM(b.monto * m.tasa_a_usd) AS total
             FROM boletas b
             JOIN monedas m ON m.codigo = b.moneda_codigo
             WHERE b.fecha_emision BETWEEN :desde AND :hasta AND NOT b.anulada
             GROUP BY mes ORDER BY mes"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    /** KPIs del periodo (consolidados a USD, sin boletas/pagos anulados): emitido vs cobrado en el rango dado. */
    public function kpis(string $desde, string $hasta): array
    {
        $stmtFacturado = $this->db->prepare(
            "SELECT COALESCE(SUM(b.monto * m.tasa_a_usd), 0)
             FROM boletas b JOIN monedas m ON m.codigo = b.moneda_codigo
             WHERE b.fecha_emision BETWEEN :desde AND :hasta AND NOT b.anulada"
        );
        $stmtFacturado->execute([':desde' => $desde, ':hasta' => $hasta]);
        $facturado = (float) $stmtFacturado->fetchColumn();

        $stmtCobrado = $this->db->prepare(
            "SELECT COALESCE(SUM(p.monto * m.tasa_a_usd), 0)
             FROM pagos p JOIN monedas m ON m.codigo = p.moneda_codigo
             WHERE p.fecha_pago BETWEEN :desde AND :hasta AND NOT p.anulada"
        );
        $stmtCobrado->execute([':desde' => $desde, ':hasta' => $hasta]);
        $cobrado = (float) $stmtCobrado->fetchColumn();

        return [
            'facturado' => $facturado,
            'cobrado' => $cobrado,
            'tasa_cobranza' => $facturado > 0 ? $cobrado / $facturado : 0.0,
        ];
    }

    /** Saldo pendiente de la cartera (consolidado a USD, sin boletas anuladas), agrupado por antigüedad de vencimiento. */
    public function carteraAging(): array
    {
        $rows = $this->db->query(
            "SELECT b.id, b.fecha_vencimiento, m.tasa_a_usd,
                    b.monto - COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id AND NOT p.anulada), 0) AS saldo,
                    (CURRENT_DATE - b.fecha_vencimiento) AS dias_vencido
             FROM boletas b
             JOIN monedas m ON m.codigo = b.moneda_codigo
             WHERE NOT b.anulada"
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

    /**
     * Todo el historial de boletas de un cliente puntual (para su ficha), sin
     * filtro de fecha. Incluye las anuladas (quedan marcadas, no se ocultan).
     */
    public function porCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.id, b.concepto, b.monto, b.moneda_codigo, b.fecha_emision, b.fecha_vencimiento, b.anulada,
                    COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id AND NOT p.anulada), 0) AS pagado
             FROM boletas b
             WHERE b.cliente_id = :id
             ORDER BY b.fecha_emision DESC"
        );
        $stmt->execute([':id' => $clienteId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $calculo = EstadoBoleta::calcular(
                (float) $row['monto'],
                (float) $row['pagado'],
                $row['fecha_vencimiento'],
                null,
                (bool) $row['anulada']
            );
            $row['saldo'] = $calculo['saldo'];
            $row['estado'] = $calculo['estado'];
        }

        return $rows;
    }

    /**
     * Listado paginado para la pantalla de Cobros. Incluye anuladas (con su
     * badge) salvo que se filtre explicitamente por otro estado.
     *
     * @return array{filas: array, total: int, totalPaginas: int}
     */
    public function listado(string $desde, string $hasta, ?string $estado = null, ?string $cliente = null, int $pagina = 1): array
    {
        $params = [':desde' => $desde, ':hasta' => $hasta];
        $filtroCliente = '';
        if ($cliente !== null && $cliente !== '') {
            $filtroCliente = ' AND c.nombre ILIKE :cliente';
            $params[':cliente'] = '%' . $cliente . '%';
        }

        $stmt = $this->db->prepare(
            "SELECT b.id, b.concepto, b.monto, b.moneda_codigo, b.fecha_emision, b.fecha_vencimiento, b.anulada,
                    c.id AS cliente_id, c.nombre AS cliente,
                    COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id AND NOT p.anulada), 0) AS pagado
             FROM boletas b
             JOIN clientes c ON c.id = b.cliente_id
             WHERE b.fecha_emision BETWEEN :desde AND :hasta{$filtroCliente}
             ORDER BY b.fecha_emision DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $filtradas = [];
        foreach ($rows as $row) {
            $calculo = EstadoBoleta::calcular(
                (float) $row['monto'],
                (float) $row['pagado'],
                $row['fecha_vencimiento'],
                null,
                (bool) $row['anulada']
            );

            if ($estado !== null && $estado !== '' && $calculo['estado'] !== $estado) {
                continue;
            }

            $row['saldo'] = $calculo['saldo'];
            $row['estado'] = $calculo['estado'];
            $filtradas[] = $row;
        }

        $total = count($filtradas);

        return [
            'filas' => array_slice($filtradas, Paginacion::offset($pagina), Paginacion::POR_PAGINA),
            'total' => $total,
            'totalPaginas' => Paginacion::totalPaginas($total),
        ];
    }
}
