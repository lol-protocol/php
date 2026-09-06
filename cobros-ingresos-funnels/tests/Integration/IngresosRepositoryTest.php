<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\IngresosRepository;
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

    public function testCobrosPorMesNuncaEsNegativo(): void
    {
        $filas = (new IngresosRepository())->cobrosPorMes('2000-01-01', '2100-01-01');

        foreach ($filas as $fila) {
            self::assertGreaterThanOrEqual(0.0, (float) $fila['total']);
        }
    }

    public function testPorMetodoSumaLoMismoQueCobrosPorMes(): void
    {
        $repo = new IngresosRepository();
        $totalPorMetodo = array_sum(array_column($repo->porMetodo('2000-01-01', '2100-01-01'), 'total'));
        $totalPorMes = array_sum(array_column($repo->cobrosPorMes('2000-01-01', '2100-01-01'), 'total'));

        self::assertEqualsWithDelta($totalPorMes, $totalPorMetodo, 0.05);
    }
}
