<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Reporting de ingresos y cobros (boletas + pagos), consolidado a USD.
 * Separado del CRUD de BoletaRepository/PagoRepository porque responde a una
 * pregunta distinta ("como viene la plata") en vez de "leer/escribir una fila".
 *
 * Los ingresos y los cobros se convierten con la tasa que cada fila guardo al
 * crearse (tasa_a_usd, migracion 011): un mes cerrado da siempre la misma cifra,
 * aunque la tasa de la moneda cambie despues. La cartera pendiente es la unica
 * que usa la tasa de hoy (monedas.tasa_a_usd): es plata por cobrar, y se valua a
 * lo que vale hoy.
 */
final class IngresosYCobrosRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * KPIs del periodo (consolidados a USD a la tasa de cada dia, sin
     * boletas/pagos anulados): emitido vs cobrado en el rango dado. "cobrado" va neto de las notas
     * de credito del periodo: si se anulo una boleta que ya estaba cobrada,
     * esa plata se devuelve y no puede seguir contando como ingreso.
     */
    public function kpis(string $desde, string $hasta): array
    {
        $stmtFacturado = $this->db->prepare(
            "SELECT COALESCE(SUM(b.monto * b.tasa_a_usd), 0)
             FROM boletas b
             WHERE b.fecha_emision BETWEEN :desde AND :hasta AND NOT b.anulada"
        );
        $stmtFacturado->execute([':desde' => $desde, ':hasta' => $hasta]);
        $facturado = (float) $stmtFacturado->fetchColumn();

        $stmtCobrado = $this->db->prepare(
            "SELECT COALESCE(SUM(p.monto * p.tasa_a_usd), 0)
             FROM pagos p
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

    /** Ingresos devengados (boletas emitidas, sin anular), en USD a la tasa del dia de cada boleta, agrupados por mes de emision. */
    public function ingresosPorMes(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT to_char(b.fecha_emision, 'YYYY-MM') AS mes, SUM(b.monto * b.tasa_a_usd) AS total
             FROM boletas b
             WHERE b.fecha_emision BETWEEN :desde AND :hasta AND NOT b.anulada
             GROUP BY mes ORDER BY mes"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    /**
     * Los tramos de antiguedad de la cartera: etiqueta => ultimo dia de vencida
     * que incluye (null = sin tope). Es la unica lista: tramoDeAntiguedad() la
     * recorre y carteraAging() arma de ella el CASE de SQL, asi que las dos
     * formas de clasificar una boleta no pueden discrepar.
     */
    private const TRAMOS = ['Al día' => 0, '1-30 días' => 30, '31-60 días' => 60, '61+ días' => null];

    /**
     * Saldo pendiente de la cartera (sin boletas anuladas), agrupado por
     * antigüedad de vencimiento. Es lo que se debe hoy, asi que se valua en USD a
     * la tasa de hoy (monedas.tasa_a_usd) y no a la que tenia cada boleta al
     * emitirse: es el unico total en USD que se mueve con la tasa.
     *
     * Se suma en SQL y responde 4 filas. Antes traia las 117 mil boletas de una
     * base mediana a PHP y las recorria en un foreach: 668 ms, 58 MB y crecia en
     * linea recta con las boletas. Lo pagado se agrega una sola vez por boleta
     * (un hash join) y no con una subconsulta por fila como hace la vista
     * boletas_con_saldo, que es lo que conviene para pocas boletas pero no para
     * todas. Un saldo de un centavo o menos no es cartera (puede ser un
     * redondeo).
     *
     * @return array<string, float> etiqueta del tramo => saldo en USD, en el orden de TRAMOS
     */
    public function carteraAging(): array
    {
        $filas = $this->db->query(
            'SELECT ' . self::tramoSql('x.dias_vencido') . ' AS tramo, SUM(x.saldo_usd) AS total
             FROM (
                 SELECT (CURRENT_DATE - b.fecha_vencimiento) AS dias_vencido,
                        (b.monto - COALESCE(p.pagado, 0)) * m.tasa_a_usd AS saldo_usd
                 FROM boletas b
                 JOIN monedas m ON m.codigo = b.moneda_codigo
                 LEFT JOIN (SELECT boleta_id, SUM(monto) AS pagado
                            FROM pagos WHERE NOT anulada AND boleta_id IS NOT NULL
                            GROUP BY boleta_id) p ON p.boleta_id = b.id
                 WHERE NOT b.anulada
             ) x
             WHERE x.saldo_usd > 0.01
             GROUP BY 1'
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $etiquetas = array_keys(self::TRAMOS);
        $buckets = array_fill_keys($etiquetas, 0.0);
        foreach ($filas as $tramo => $total) {
            $buckets[$etiquetas[(int) $tramo]] = (float) $total;
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
        foreach (self::TRAMOS as $etiqueta => $hastaDia) {
            if ($hastaDia !== null && $diasVencido <= $hastaDia) {
                return $etiqueta;
            }
        }

        return (string) array_key_last(self::TRAMOS);   // el ultimo tramo no tiene tope
    }

    /**
     * El CASE de SQL que da el indice del tramo (0, 1, 2...) de una cantidad de
     * dias vencida, armado de TRAMOS. $dias es siempre una expresion fija del
     * codigo, nunca entrada de usuario.
     */
    private static function tramoSql(string $dias): string
    {
        $casos = '';
        $indice = 0;
        foreach (self::TRAMOS as $hastaDia) {
            if ($hastaDia === null) {
                break;
            }
            $casos .= " WHEN {$dias} <= {$hastaDia} THEN {$indice}";
            $indice++;
        }

        return "CASE{$casos} ELSE {$indice} END";
    }

    /**
     * Cobros por mes (sin pagos anulados y netos de devoluciones), en USD a
     * la tasa del dia de cada pago y de cada nota de credito.
     *
     * Se fusionan los meses de las dos fuentes, no solo los que tienen
     * pagos: un mes que solo tuvo devoluciones tambien es un mes con
     * movimiento, y si se lo salteara el grafico no cerraria con el KPI de
     * cobrado (que si las cuenta). Por eso un mes puede dar negativo.
     */
    public function cobrosPorMes(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT to_char(p.fecha_pago, 'YYYY-MM') AS mes, SUM(p.monto * p.tasa_a_usd) AS total
             FROM pagos p
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

    /** Total por metodo de pago (sin anulados), en USD a la tasa del dia de cada pago. */
    public function porMetodo(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.metodo, SUM(p.monto * p.tasa_a_usd) AS total, COUNT(*) AS cantidad
             FROM pagos p
             WHERE p.fecha_pago BETWEEN :desde AND :hasta AND NOT p.anulada
             GROUP BY p.metodo ORDER BY total DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }
}
