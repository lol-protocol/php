<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use DateTimeImmutable;
use PDO;

/**
 * El flujo de caja esperado: lo que falta cobrar de las boletas abiertas, repartido por el mes en que
 * vence cada una. Responde «cuanto deberia entrar en cada mes», que es otra pregunta que la antiguedad
 * de la cartera (cuanto lleva vencido lo que ya vencio) y que Cobranza (quien debe).
 *
 * Sale de la misma consulta que la cartera del Dashboard y la lista de Cobranza
 * (IngresosYCobrosRepository::saldosAbiertosSql()): las boletas vigentes con mas de un centavo de saldo,
 * en USD a la tasa de hoy. Por eso la suma de todas las cubetas es exactamente la «cartera pendiente».
 *
 * Supone que cada boleta se cobra el dia de su vencimiento. No es una prediccion de cuando va a pagar cada
 * cliente: lo ya vencido va aparte, en su propia cubeta, justamente porque no se cobro cuando tocaba.
 */
final class FlujoDeCajaRepository
{
    /** Cuantos meses hacia adelante se pueden mostrar, con el texto del selector. */
    public const HORIZONTES = [3 => 'Próximos 3 meses', 6 => 'Próximos 6 meses', 12 => 'Próximos 12 meses'];

    public const MESES_POR_DEFECTO = 6;

    /** Las claves de las dos cubetas que no son un mes. */
    public const VENCIDO = 'vencido';
    public const DESPUES = 'despues';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Las cubetas del flujo, en orden y siempre todas (una sin boletas sale en cero): lo ya vencido, cada
     * uno de los $meses meses desde el actual (el actual incluye lo que vence hoy) y lo que vence despues
     * del horizonte. Cada una: clave (self::VENCIDO, 'YYYY-MM' o self::DESPUES), boletas, clientes
     * (distintos) y saldo_usd. El texto de cada una lo pone quien dibuja.
     *
     * @return list<array{clave: string, boletas: int, clientes: int, saldo_usd: float}>
     */
    public function cubetas(int $meses = self::MESES_POR_DEFECTO): array
    {
        $meses = max(1, $meses);

        // idx = cuantos meses faltan para el del vencimiento (0 es este mes). Lo que ya vencio va a la -1;
        // lo que pasa del horizonte, a la $meses. Hoy cuenta como este mes: una boleta que vence hoy no esta vencida.
        $stmt = $this->db->prepare(
            'SELECT CASE WHEN s.dias_vencido > 0 THEN -1
                         WHEN m.idx >= :meses THEN :meses
                         ELSE m.idx END AS cubeta,
                    COUNT(*) AS boletas,
                    COUNT(DISTINCT s.cliente_id) AS clientes,
                    SUM(s.saldo_usd) AS saldo_usd
             FROM (' . IngresosYCobrosRepository::saldosAbiertosSql() . ') s
             CROSS JOIN LATERAL (
                 SELECT (EXTRACT(YEAR FROM s.fecha_vencimiento)::int * 12 + EXTRACT(MONTH FROM s.fecha_vencimiento)::int)
                      - (EXTRACT(YEAR FROM CURRENT_DATE)::int * 12 + EXTRACT(MONTH FROM CURRENT_DATE)::int) AS idx
             ) m
             GROUP BY 1'
        );
        $stmt->bindValue(':meses', $meses, PDO::PARAM_INT);
        $stmt->execute();

        $porCubeta = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porCubeta[(int) $fila['cubeta']] = $fila;
        }

        $primerMes = new DateTimeImmutable('first day of this month');
        $armar = static function (string $clave, int $cubeta) use ($porCubeta): array {
            $fila = $porCubeta[$cubeta] ?? null;

            return [
                'clave' => $clave,
                'boletas' => $fila === null ? 0 : (int) $fila['boletas'],
                'clientes' => $fila === null ? 0 : (int) $fila['clientes'],
                'saldo_usd' => $fila === null ? 0.0 : (float) $fila['saldo_usd'],
            ];
        };

        $cubetas = [$armar(self::VENCIDO, -1)];
        for ($i = 0; $i < $meses; $i++) {
            $cubetas[] = $armar($primerMes->modify("+{$i} months")->format('Y-m'), $i);
        }
        $cubetas[] = $armar(self::DESPUES, $meses);

        return $cubetas;
    }

    /**
     * Los totales de las cubetas: lo que falta cobrar en total (la cartera pendiente), cuanto de eso ya
     * esta vencido, cuanto vence en lo que queda de este mes y cuantas boletas hay detras. Los clientes no
     * se suman entre cubetas (uno con boletas en dos meses estaria dos veces), por eso no hay un total.
     *
     * @param list<array{clave: string, boletas: int, clientes: int, saldo_usd: float}> $cubetas
     * @return array{total_usd: float, vencido_usd: float, este_mes_usd: float, boletas: int}
     */
    public static function totales(array $cubetas): array
    {
        $total = 0.0;
        $boletas = 0;
        foreach ($cubetas as $cubeta) {
            $total += $cubeta['saldo_usd'];
            $boletas += $cubeta['boletas'];
        }

        return [
            'total_usd' => $total,
            'vencido_usd' => $cubetas[0]['saldo_usd'],
            'este_mes_usd' => $cubetas[1]['saldo_usd'],
            'boletas' => $boletas,
        ];
    }
}
