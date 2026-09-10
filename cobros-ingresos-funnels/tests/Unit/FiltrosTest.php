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

    public function testRangoAnioAnteriorRestaExactamenteUnAnioCalendario(): void
    {
        [$desde, $hasta] = Filtros::rangoAnioAnterior('2026-06-01', '2026-09-01');

        self::assertSame('2025-06-01', $desde);
        self::assertSame('2025-09-01', $hasta);
    }

    public function testRangoAnioAnteriorManeja29DeFebreroEnAnioBisiesto(): void
    {
        // DateTimeImmutable::modify('-1 year') sobre 29-feb (bisiesto) cae en
        // 28-feb del anio siguiente no bisiesto; documentamos ese comportamiento.
        [$desde] = Filtros::rangoAnioAnterior('2024-02-29', '2024-03-01');

        self::assertSame('2023-03-01', $desde);
    }

    public function testRangoPersonalizadoNuloSinParametros(): void
    {
        unset($_GET['desde'], $_GET['hasta']);
        self::assertNull(Filtros::rangoPersonalizado());
    }

    public function testRangoPersonalizadoNuloConFechaInvalida(): void
    {
        $_GET['desde'] = '2026-13-40';
        $_GET['hasta'] = '2026-09-01';
        self::assertNull(Filtros::rangoPersonalizado());
    }

    public function testRangoPersonalizadoNuloCuandoDesdeEsPosteriorAHasta(): void
    {
        $_GET['desde'] = '2026-09-01';
        $_GET['hasta'] = '2026-06-01';
        self::assertNull(Filtros::rangoPersonalizado());

        unset($_GET['desde'], $_GET['hasta']);
    }

    public function testRangoPersonalizadoValidoDevuelveLasMismasFechas(): void
    {
        $_GET['desde'] = '2026-01-15';
        $_GET['hasta'] = '2026-02-20';

        self::assertSame(['2026-01-15', '2026-02-20'], Filtros::rangoPersonalizado());

        unset($_GET['desde'], $_GET['hasta']);
    }

    public function testRangoActivoUsaElPersonalizadoCuandoEstaPresente(): void
    {
        $_GET['desde'] = '2026-01-15';
        $_GET['hasta'] = '2026-02-20';

        self::assertSame(['2026-01-15', '2026-02-20'], Filtros::rangoActivo());

        unset($_GET['desde'], $_GET['hasta']);
    }

    public function testRangoActivoCaeAlRangoPorMesesSinPersonalizado(): void
    {
        unset($_GET['desde'], $_GET['hasta']);
        $_GET['meses'] = '3';

        self::assertSame(Filtros::rango(3), Filtros::rangoActivo());

        unset($_GET['meses']);
    }

    public function testEsFechaValidaAceptaFechasReales(): void
    {
        self::assertTrue(Filtros::esFechaValida('2026-01-15'));
        self::assertTrue(Filtros::esFechaValida('2024-02-29'), '2024 es bisiesto');
    }

    public function testEsFechaValidaRechazaTextoSuelto(): void
    {
        self::assertFalse(Filtros::esFechaValida('esto-no-es-una-fecha'));
        self::assertFalse(Filtros::esFechaValida(''));
    }

    public function testEsFechaValidaRechazaFechasImposiblesAunqueTenganElFormatoCorrecto(): void
    {
        self::assertFalse(Filtros::esFechaValida('2026-13-40'), 'mes 13 no existe');
        self::assertFalse(Filtros::esFechaValida('2025-02-29'), '2025 no es bisiesto');
    }
}
