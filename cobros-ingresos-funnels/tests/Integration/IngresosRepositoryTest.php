<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\IngresosRepository;
use App\Repositories\NotaCreditoRepository;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*. Requiere haber
 * corrido antes `php database/seed.php` (mismas variables) para tener datos.
 */
final class IngresosRepositoryTest extends TestCase
{
    public function testCarteraAgingSoloSumaSaldosPositivosYCoincideConLaSumaIndependiente(): void
    {
        $buckets = (new IngresosRepository())->carteraAging();

        self::assertSame(['Al dia', '1-30 dias', '31-60 dias', '61+ dias'], array_keys($buckets));
        foreach ($buckets as $monto) {
            self::assertGreaterThanOrEqual(0.0, $monto);
        }

        $sumaBuckets = array_sum($buckets);
        $sumaIndependiente = (float) Database::connection()->query(
            "SELECT COALESCE(SUM(saldo_usd), 0) FROM (
                SELECT GREATEST(
                    b.monto - COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id AND NOT p.anulada), 0), 0
                ) * m.tasa_a_usd AS saldo_usd
                FROM boletas b JOIN monedas m ON m.codigo = b.moneda_codigo
                WHERE NOT b.anulada
            ) sub WHERE saldo_usd > 0.01"
        )->fetchColumn();

        self::assertEqualsWithDelta($sumaIndependiente, $sumaBuckets, 0.05);
    }

    public function testKpisTasaDeCobranzaEsCoherenteConFacturadoYCobrado(): void
    {
        $kpis = (new IngresosRepository())->kpis('2000-01-01', '2100-01-01');

        self::assertGreaterThanOrEqual(0.0, $kpis['facturado']);
        self::assertGreaterThanOrEqual(0.0, $kpis['cobrado']);

        if ($kpis['facturado'] > 0) {
            self::assertEqualsWithDelta($kpis['cobrado'] / $kpis['facturado'], $kpis['tasa_cobranza'], 0.0001);
        } else {
            self::assertSame(0.0, $kpis['tasa_cobranza']);
        }
    }

    /**
     * La suma del grafico mensual tiene que dar exactamente el mismo numero
     * que el KPI de cobrado: son dos vistas del mismo dato, una al lado de
     * la otra en la pantalla. Un mes ya no puede "no existir" por no tener
     * pagos: si tuvo devoluciones, aparece igual (en negativo).
     */
    public function testLaSumaDeCobrosPorMesCoincideConElKpiDeCobrado(): void
    {
        $repo = new IngresosRepository();
        $sumaMensual = array_sum(array_column($repo->cobrosPorMes('2000-01-01', '2100-01-01'), 'total'));

        self::assertEqualsWithDelta($repo->kpis('2000-01-01', '2100-01-01')['cobrado'], $sumaMensual, 0.05);
    }

    /**
     * Reproduce el bug real: una nota de credito en un mes sin ningun pago
     * se perdia del grafico (0 filas) mientras el KPI si la contaba, asi que
     * las dos cifras de la misma pantalla se contradecian.
     */
    public function testUnMesConSoloDevolucionesAparaceEnElGrafico(): void
    {
        $db = Database::connection();
        $boleta = $db->query('SELECT id, cliente_id, moneda_codigo FROM boletas ORDER BY id LIMIT 1')->fetch();
        self::assertNotFalse($boleta, 'este test asume que el seed dejo al menos una boleta');

        $notaId = (new NotaCreditoRepository())->crear([
            'boleta_id' => $boleta['id'],
            'cliente_id' => $boleta['cliente_id'],
            'monto' => 1000.00,
            'moneda_codigo' => $boleta['moneda_codigo'],
            'fecha' => '1990-06-15',
            'motivo' => 'Nota de prueba ' . uniqid(),
        ]);

        try {
            $repo = new IngresosRepository();
            $filas = $repo->cobrosPorMes('1990-01-01', '1990-12-31');

            self::assertCount(1, $filas, 'el mes con solo devoluciones tiene que aparecer igual');
            self::assertSame('1990-06', $filas[0]['mes']);
            self::assertLessThan(0.0, (float) $filas[0]['total'], 'un mes que solo devolvio plata da negativo');
            self::assertEqualsWithDelta(
                $repo->kpis('1990-01-01', '1990-12-31')['cobrado'],
                (float) $filas[0]['total'],
                0.05,
                'grafico y KPI tienen que decir lo mismo'
            );
        } finally {
            $db->prepare('DELETE FROM notas_credito WHERE id = :id')->execute([':id' => $notaId]);
        }
    }

    /**
     * porMetodo() es bruto a proposito (responde "por que canal entro la
     * plata", y una devolucion no es un canal), mientras que cobrosPorMes()
     * va neto de notas de credito. La relacion entre los dos, entonces, es
     * bruto = neto + devoluciones; es lo que la pantalla de Pagos muestra
     * desglosado para que los numeros reconcilien a la vista.
     */
    public function testPorMetodoEsElBrutoYCobrosPorMesElNetoDeDevoluciones(): void
    {
        $repo = new IngresosRepository();
        $totalPorMetodo = array_sum(array_column($repo->porMetodo('2000-01-01', '2100-01-01'), 'total'));
        $totalPorMes = array_sum(array_column($repo->cobrosPorMes('2000-01-01', '2100-01-01'), 'total'));
        $devoluciones = (new NotaCreditoRepository())->totalEnRangoUsd('2000-01-01', '2100-01-01');

        self::assertEqualsWithDelta($totalPorMes + $devoluciones, $totalPorMetodo, 0.05);
    }

    public function testKpisDescuentaLasDevolucionesDelCobradoBruto(): void
    {
        $kpis = (new IngresosRepository())->kpis('2000-01-01', '2100-01-01');

        self::assertEqualsWithDelta($kpis['cobrado_bruto'] - $kpis['devoluciones'], $kpis['cobrado'], 0.0001);
        self::assertGreaterThanOrEqual(0.0, $kpis['devoluciones']);
    }
}
