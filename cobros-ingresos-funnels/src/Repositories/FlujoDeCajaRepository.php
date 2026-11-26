<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Dinero;
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
 * Cuando se espera cada cobro depende del supuesto (SUPUESTOS): el dia del vencimiento, o ese dia mas el atraso
 * habitual del cliente (atrasoHabitualSql()). Con cualquiera de los dos la suma de las cubetas es la misma: el
 * supuesto solo cambia en que mes cae cada boleta. Lo ya vencido va aparte, en su propia cubeta, en los dos
 * supuestos: justamente porque no se cobro cuando tocaba, no hay una fecha esperada que ponerle.
 */
final class FlujoDeCajaRepository
{
    /** Cuantos meses hacia adelante se pueden mostrar, con el texto del selector. */
    public const HORIZONTES = [3 => 'Próximos 3 meses', 6 => 'Próximos 6 meses', 12 => 'Próximos 12 meses'];

    public const MESES_POR_DEFECTO = 6;

    /** Las claves de las dos cubetas que no son un mes. */
    public const VENCIDO = 'vencido';
    public const DESPUES = 'despues';

    /** Cuando se espera cobrar lo que todavia no vence. La clave va en la URL (?supuesto=). */
    public const SUPUESTO_ATRASO = 'atraso';
    public const SUPUESTO_VENCIMIENTO = 'vencimiento';
    public const SUPUESTOS = [
        self::SUPUESTO_ATRASO => 'Con el atraso habitual de cada cliente',
        self::SUPUESTO_VENCIMIENTO => 'El día del vencimiento',
    ];
    public const SUPUESTO_POR_DEFECTO = self::SUPUESTO_ATRASO;

    /** Boletas pagadas completas que hacen falta para saber cuanto se atrasa un cliente: con menos no hay habito, hay azar. */
    public const BOLETAS_MINIMAS_DE_HISTORIAL = 3;

    /** El texto de esas dos cubetas, en la pantalla y en el CSV; un mes se escribe cada uno a su manera. */
    public const ETIQUETAS_FIJAS = [self::VENCIDO => 'Ya vencido', self::DESPUES => 'Más adelante'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Las cubetas del flujo, en orden y siempre todas (una sin boletas sale en cero): lo ya vencido, cada
     * uno de los $meses meses desde el actual (el actual incluye lo que se espera cobrar hoy) y lo que se
     * espera despues del horizonte. Cada una: clave (self::VENCIDO, 'YYYY-MM' o self::DESPUES), boletas, clientes
     * (distintos), saldo_usd y con_historial_usd (cuanto de ese saldo se movio por el atraso habitual de su
     * cliente; cero con el supuesto del vencimiento y en lo ya vencido). El texto de cada una lo pone quien dibuja.
     *
     * Con SUPUESTO_VENCIMIENTO cada boleta por vencer cae en el mes de su vencimiento. Con SUPUESTO_ATRASO, en el mes
     * de su vencimiento mas el atraso habitual de su cliente (atrasoHabitualSql()); un cliente sin historial queda
     * en su vencimiento. Un supuesto que no es ninguno de los dos se toma como el del vencimiento.
     *
     * @return list<array{clave: string, boletas: int, clientes: int, saldo_usd: float, con_historial_usd: float}>
     */
    public function cubetas(int $meses = self::MESES_POR_DEFECTO, string $supuesto = self::SUPUESTO_POR_DEFECTO): array
    {
        $meses = max(1, $meses);
        $conAtraso = $supuesto === self::SUPUESTO_ATRASO;
        // Fragmentos fijos de este archivo, nunca entrada de usuario.
        $union = $conAtraso ? 'LEFT JOIN (' . self::atrasoHabitualSql() . ') a ON a.cliente_id = s.cliente_id' : '';
        $dias = $conAtraso ? 'COALESCE(a.dias, 0)' : '0';
        $movidoPorElAtraso = $conAtraso ? 'COALESCE(SUM(s.saldo_usd) FILTER (WHERE a.dias IS NOT NULL AND s.dias_vencido <= 0), 0)' : '0';

        // idx = cuantos meses faltan para el mes en que se espera el cobro (0 es este mes). Lo que ya vencio va a la -1;
        // lo que pasa del horizonte, a la $meses. Hoy cuenta como este mes: una boleta que vence hoy no esta vencida.
        $stmt = $this->db->prepare(
            "SELECT CASE WHEN s.dias_vencido > 0 THEN -1
                         WHEN m.idx >= :meses THEN :meses
                         ELSE m.idx END AS cubeta,
                    COUNT(*) AS boletas,
                    COUNT(DISTINCT s.cliente_id) AS clientes,
                    SUM(s.saldo_usd) AS saldo_usd,
                    {$movidoPorElAtraso} AS con_historial_usd
             FROM (" . IngresosYCobrosRepository::saldosAbiertosSql() . ") s
             {$union}
             CROSS JOIN LATERAL (
                 SELECT (EXTRACT(YEAR FROM s.fecha_vencimiento + {$dias})::int * 12 + EXTRACT(MONTH FROM s.fecha_vencimiento + {$dias})::int)
                      - (EXTRACT(YEAR FROM CURRENT_DATE)::int * 12 + EXTRACT(MONTH FROM CURRENT_DATE)::int) AS idx
             ) m
             GROUP BY 1"
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
                'con_historial_usd' => $fila === null ? 0.0 : (float) $fila['con_historial_usd'],
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
     * Cuantos dias se atrasa cada cliente en pagar, de lo que ya pago: una fila (cliente_id, dias) por cliente con al
     * menos BOLETAS_MINIMAS_DE_HISTORIAL boletas pagadas completas. Para cada boleta vigente y saldada (su saldo cuenta
     * como cero, Dinero::UN_CENTAVO) el atraso es la fecha de su ultimo pago vigente menos su vencimiento; un pago
     * anticipado cuenta cero y no compensa los atrasos de las demas (no se espera cobrar antes de tiempo). Los dias son
     * el promedio por boleta, redondeado. Una boleta anulada, una con pagos anulados que ya no la saldan o una con saldo
     * no entran: del atraso solo se sabe cuando la boleta termino de pagarse.
     */
    private static function atrasoHabitualSql(): string
    {
        return 'SELECT cerradas.cliente_id, round(AVG(cerradas.atraso))::int AS dias
                FROM (
                    SELECT b.cliente_id,
                           GREATEST(0, MAX(p.fecha_pago) - b.fecha_vencimiento) AS atraso
                    FROM boletas b
                    JOIN pagos p ON p.boleta_id = b.id AND NOT p.anulada
                    WHERE NOT b.anulada
                    GROUP BY b.id
                    HAVING round(b.monto - SUM(p.monto), 3) <= ' . Dinero::UN_CENTAVO . '
                ) cerradas
                GROUP BY cerradas.cliente_id
                HAVING COUNT(*) >= ' . self::BOLETAS_MINIMAS_DE_HISTORIAL;
    }

    /**
     * Los totales de las cubetas: lo que falta cobrar en total (la cartera pendiente), cuanto de eso ya
     * esta vencido, cuanto se espera cobrar en lo que queda de este mes, cuantas boletas hay detras y, de lo que
     * todavia no vence (por_vencer_usd), cuanto se movio por el atraso habitual de su cliente (con_historial_usd).
     * Los clientes no se suman entre cubetas (uno con boletas en dos meses estaria dos veces), por eso no hay un total.
     *
     * @param list<array{clave: string, boletas: int, clientes: int, saldo_usd: float, con_historial_usd: float}> $cubetas
     * @return array{total_usd: float, vencido_usd: float, este_mes_usd: float, boletas: int, por_vencer_usd: float, con_historial_usd: float}
     */
    public static function totales(array $cubetas): array
    {
        $total = 0.0;
        $boletas = 0;
        $conHistorial = 0.0;
        foreach ($cubetas as $cubeta) {
            $total += $cubeta['saldo_usd'];
            $boletas += $cubeta['boletas'];
            $conHistorial += $cubeta['con_historial_usd'];
        }

        return [
            'total_usd' => $total,
            'vencido_usd' => $cubetas[0]['saldo_usd'],
            'este_mes_usd' => $cubetas[1]['saldo_usd'],
            'boletas' => $boletas,
            'por_vencer_usd' => $total - $cubetas[0]['saldo_usd'],
            'con_historial_usd' => $conHistorial,
        ];
    }
}
