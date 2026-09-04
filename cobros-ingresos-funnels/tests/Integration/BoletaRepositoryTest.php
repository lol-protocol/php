<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*. Requiere haber
 * corrido antes `php database/seed.php` (mismas variables) para tener datos.
 */
final class BoletaRepositoryTest extends TestCase
{
    public function testCarteraAgingSoloSumaSaldosPositivosYCoincideConLaSumaIndependiente(): void
    {
        $buckets = (new BoletaRepository())->carteraAging();

        self::assertSame(['Al dia', '1-30 dias', '31-60 dias', '61+ dias'], array_keys($buckets));
        foreach ($buckets as $monto) {
            self::assertGreaterThanOrEqual(0.0, $monto);
        }

        $sumaBuckets = array_sum($buckets);
        $sumaIndependiente = (float) Database::connection()->query(
            "SELECT COALESCE(SUM(saldo_usd), 0) FROM (
                SELECT GREATEST(
                    b.monto - COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.boleta_id = b.id), 0), 0
                ) * m.tasa_a_usd AS saldo_usd
                FROM boletas b JOIN monedas m ON m.codigo = b.moneda_codigo
            ) sub WHERE saldo_usd > 0.01"
        )->fetchColumn();

        self::assertEqualsWithDelta($sumaIndependiente, $sumaBuckets, 0.05);
    }

    public function testKpisTasaDeCobranzaEsCoherenteConFacturadoYCobrado(): void
    {
        $kpis = (new BoletaRepository())->kpis('2000-01-01', '2100-01-01');

        self::assertGreaterThanOrEqual(0.0, $kpis['facturado']);
        self::assertGreaterThanOrEqual(0.0, $kpis['cobrado']);

        if ($kpis['facturado'] > 0) {
            self::assertEqualsWithDelta($kpis['cobrado'] / $kpis['facturado'], $kpis['tasa_cobranza'], 0.0001);
        } else {
            self::assertSame(0.0, $kpis['tasa_cobranza']);
        }
    }

    public function testListadoFiltradoPorEstadoSoloDevuelveEseEstado(): void
    {
        $repo = new BoletaRepository();
        $vencidas = $repo->listado('2000-01-01', '2100-01-01', 'vencida');

        foreach ($vencidas as $boleta) {
            self::assertSame('vencida', $boleta['estado']);
            self::assertLessThan(date('Y-m-d'), $boleta['fecha_vencimiento']);
        }
    }

    public function testFiltroPorClienteSoloDevuelveEseCliente(): void
    {
        $repo = new BoletaRepository();
        $todas = $repo->listado('2000-01-01', '2100-01-01');
        if ($todas === []) {
            self::markTestSkipped('No hay boletas seedeadas para probar el filtro.');
        }

        $nombreCliente = $todas[0]['cliente'];
        $filtradas = $repo->listado('2000-01-01', '2100-01-01', null, $nombreCliente);

        self::assertNotEmpty($filtradas);
        foreach ($filtradas as $b) {
            self::assertSame($nombreCliente, $b['cliente']);
        }
    }
}
