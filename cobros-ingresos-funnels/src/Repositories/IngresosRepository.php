<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Reporting de ingresos y cobros (boletas + pagos), consolidado a USD.
 * Separado del CRUD de BoletaRepository/PagoRepository porque responde a una
 * pregunta distinta ("como viene la plata") en vez de "leer/escribir una fila".
 */
final class IngresosRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * KPIs del periodo (consolidados a USD, sin boletas/pagos anulados):
     * emitido vs cobrado en el rango dado. "cobrado" va neto de las notas
     * de credito del periodo: si se anulo una boleta que ya estaba cobrada,
     * esa plata se devuelve y no puede seguir contando como ingreso.
     */
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
        $cobradoBruto = (float) $stmtCobrado->fetchColumn();

        $devoluciones = (new NotaCreditoRepository())->totalEnRangoUsd($desde, $hasta);
        $cobrado = $cobradoBruto - $devoluciones;

        return [
            'facturado' => $facturado,
            'cobrado' => $cobrado,
            'cobrado_bruto' => $cobradoBruto,
            'devoluciones' => $devoluciones,
            'tasa_cobranza' => $facturado > 0 ? $cobrado / $facturado : 0.0,
        ];
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

    /** Saldo pendiente de la cartera (consolidado a USD, sin boletas anuladas), agrupado por antigüedad de vencimiento. */
    public function carteraAging(): array
    {
        $rows = $this->db->query(
            "SELECT b.id, b.fecha_vencimiento, m.tasa_a_usd, b.saldo,
                    (CURRENT_DATE - b.fecha_vencimiento) AS dias_vencido
             FROM boletas_con_saldo b
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
            $buckets[self::tramoDeAntiguedad((int) $row['dias_vencido'])] += $saldoUsd;
        }

        return $buckets;
    }

    /**
     * Una boleta que vence hoy todavia esta al dia: es la misma regla que
     * EstadoBoleta (vencida recien cuando el vencimiento < hoy). Con "< 0" se
     * la contaba como vencida en la cartera mientras el listado la mostraba
     * pendiente.
     */
    public static function tramoDeAntiguedad(int $diasVencido): string
    {
        return match (true) {
            $diasVencido <= 0 => 'Al dia',
            $diasVencido <= 30 => '1-30 dias',
            $diasVencido <= 60 => '31-60 dias',
            default => '61+ dias',
        };
    }

    /**
     * Cobros por mes (sin pagos anulados y netos de devoluciones),
     * consolidados a USD.
     *
     * Se fusionan los meses de las dos fuentes, no solo los que tienen
     * pagos: un mes que solo tuvo devoluciones tambien es un mes con
     * movimiento, y si se lo salteara el grafico no cerraria con el KPI de
     * cobrado (que si las cuenta). Por eso un mes puede dar negativo.
     */
    public function cobrosPorMes(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT to_char(p.fecha_pago, 'YYYY-MM') AS mes, SUM(p.monto * m.tasa_a_usd) AS total
             FROM pagos p
             JOIN monedas m ON m.codigo = p.moneda_codigo
             WHERE p.fecha_pago BETWEEN :desde AND :hasta AND NOT p.anulada
             GROUP BY mes"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);

        $porMes = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porMes[$fila['mes']] = (float) $fila['total'];
        }
        foreach ((new NotaCreditoRepository())->porMesUsd($desde, $hasta) as $mes => $devuelto) {
            $porMes[$mes] = ($porMes[$mes] ?? 0.0) - $devuelto;
        }
        ksort($porMes);

        $filas = [];
        foreach ($porMes as $mes => $total) {
            $filas[] = ['mes' => $mes, 'total' => $total];
        }

        return $filas;
    }

    /** Total por metodo de pago (sin anulados), consolidado a USD. */
    public function porMetodo(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.metodo, SUM(p.monto * m.tasa_a_usd) AS total, COUNT(*) AS cantidad
             FROM pagos p
             JOIN monedas m ON m.codigo = p.moneda_codigo
             WHERE p.fecha_pago BETWEEN :desde AND :hasta AND NOT p.anulada
             GROUP BY p.metodo ORDER BY total DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }
}
