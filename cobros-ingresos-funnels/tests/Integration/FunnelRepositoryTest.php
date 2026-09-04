<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repositories\FunnelRepository;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*. Requiere haber
 * corrido antes `php database/seed.php` (mismas variables) para tener datos.
 */
final class FunnelRepositoryTest extends TestCase
{
    public function testResumenEtapasNuncaSuperaElTotalDeVisitantes(): void
    {
        $r = (new FunnelRepository())->resumenEtapas('2000-01-01', '2100-01-01');

        self::assertLessThanOrEqual($r['visitantes'], $r['registrados']);
        self::assertLessThanOrEqual($r['visitantes'], $r['leads']);
        self::assertLessThanOrEqual($r['visitantes'], $r['clientes']);
        foreach ($r as $valor) {
            self::assertGreaterThanOrEqual(0, $valor);
        }
    }

    public function testCohortesSonAcumulativasYMonotonasCrecientes(): void
    {
        $filas = (new FunnelRepository())->cohortes('2000-01-01', '2100-01-01');

        if ($filas === []) {
            self::markTestSkipped('No hay usuarios de funnel seedeados.');
        }

        foreach ($filas as $fila) {
            self::assertLessThanOrEqual($fila['m1'], $fila['m0'], "cohorte {$fila['cohorte']}: m0 no puede superar a m1");
            self::assertLessThanOrEqual($fila['m2'], $fila['m1'], "cohorte {$fila['cohorte']}: m1 no puede superar a m2");
            self::assertLessThanOrEqual($fila['m3'], $fila['m2'], "cohorte {$fila['cohorte']}: m2 no puede superar a m3");
            self::assertLessThanOrEqual($fila['total'], $fila['m3'], "cohorte {$fila['cohorte']}: ninguna columna puede superar el total");
        }
    }

    public function testPorCanalSumaLoMismoQueElResumenGeneral(): void
    {
        $funnelRepo = new FunnelRepository();
        $resumen = $funnelRepo->resumenEtapas('2000-01-01', '2100-01-01');
        $porCanal = $funnelRepo->porCanal('2000-01-01', '2100-01-01');

        self::assertSame($resumen['visitantes'], array_sum(array_column($porCanal, 'visitantes')));
        self::assertSame($resumen['clientes'], array_sum(array_column($porCanal, 'clientes')));
    }
}
