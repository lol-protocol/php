<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Filtros;
use PHPUnit\Framework\TestCase;

final class FiltrosTest extends TestCase
{
    public function testMesesSoloAceptaValoresPermitidos(): void
    {
        $_GET['meses'] = '12';
        self::assertSame(12, Filtros::meses());

        $_GET['meses'] = '999';
        self::assertSame(6, Filtros::meses(), 'un valor no permitido cae al default de 6');

        unset($_GET['meses']);
        self::assertSame(6, Filtros::meses(), 'sin parametro tambien cae al default de 6');
    }

    public function testRangoTerminaHoyYEmpiezaNMesesAntes(): void
    {
        [$desde, $hasta] = Filtros::rango(3);

        self::assertSame(date('Y-m-d'), $hasta);
        self::assertSame(
            (new \DateTimeImmutable('today'))->modify('-3 months')->format('Y-m-d'),
            $desde
        );
    }

    public function testRangoAnteriorEsInmediatamenteAnteriorYDelMismoLargo(): void
    {
        [$desdeAnterior, $hastaAnterior] = Filtros::rangoAnterior('2026-06-01', '2026-09-01');

        self::assertSame('2026-05-31', $hastaAnterior, 'termina el dia justo antes de que empiece el periodo actual');
        self::assertSame('2026-02-28', $desdeAnterior, 'mismo largo (92 dias) que el periodo actual');
    }

    public function testRangoAnteriorNoSuperponeConElRangoActual(): void
    {
        [$desde, $hasta] = Filtros::rango(6);
        [, $hastaAnterior] = Filtros::rangoAnterior($desde, $hasta);

        self::assertLessThan($desde, $hastaAnterior);
    }
}
