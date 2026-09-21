<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Filtros;
use DateTimeImmutable;
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
            Filtros::restarMeses(new DateTimeImmutable('today'), 3)->format('Y-m-d'),
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

    /**
     * El 29 de febrero no existe un anio antes, y DateTimeImmutable resuelve
     * ese modify('-1 year') desbordando al 1 de marzo. El equivalente real es
     * el ultimo dia de febrero: si no, el comparativo interanual arranca un
     * dia tarde y se pierde el 28.
     */
    public function testRangoAnioAnteriorManeja29DeFebreroEnAnioBisiesto(): void
    {
        [$desde] = Filtros::rangoAnioAnterior('2024-02-29', '2024-03-01');

        self::assertSame('2023-02-28', $desde);
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

        $contexto = Filtros::rangoActivo();

        self::assertSame('2026-01-15', $contexto['desde']);
        self::assertSame('2026-02-20', $contexto['hasta']);
        self::assertTrue($contexto['personalizado']);

        unset($_GET['desde'], $_GET['hasta']);
    }

    public function testRangoActivoCaeAlRangoPorMesesSinPersonalizado(): void
    {
        unset($_GET['desde'], $_GET['hasta']);
        $_GET['meses'] = '3';

        $contexto = Filtros::rangoActivo();

        self::assertSame(3, $contexto['meses']);
        self::assertSame(Filtros::rango(3), [$contexto['desde'], $contexto['hasta']]);
        self::assertFalse($contexto['personalizado']);

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

    /**
     * Reproduce el bug real: "ultimos 3 meses" un 31 de mayo arrancaba el 3
     * de marzo, porque febrero no tiene 31 dias y modify() se desbordaba al
     * mes siguiente. Pasaba sin avisar unos 4 a 7 dias por mes.
     */
    public function testRestarMesesNoSeDesbordaAlMesSiguiente(): void
    {
        $casos = [
            ['2026-05-31', 3, '2026-02-28'],
            ['2026-05-31', 6, '2025-11-30'],
            ['2026-08-31', 6, '2026-02-28'],
            ['2026-03-31', 1, '2026-02-28'],
            ['2028-03-29', 1, '2028-02-29'],
        ];

        foreach ($casos as [$hoy, $meses, $esperado]) {
            self::assertSame(
                $esperado,
                Filtros::restarMeses(new DateTimeImmutable($hoy), $meses)->format('Y-m-d'),
                "{$hoy} menos {$meses} meses"
            );
        }
    }

    public function testRestarMesesDejaIntactaUnaFechaQueSiExisteEnElMesDestino(): void
    {
        self::assertSame('2026-03-20', Filtros::restarMeses(new DateTimeImmutable('2026-09-20'), 6)->format('Y-m-d'));
        self::assertSame('2025-09-20', Filtros::restarMeses(new DateTimeImmutable('2026-09-20'), 12)->format('Y-m-d'));
    }

    /** El desde de rango() nunca puede caer en un mes posterior al que corresponde. */
    public function testRangoNoAdelantaElMesDeInicio(): void
    {
        foreach ([3, 6, 12] as $meses) {
            [$desde, $hasta] = Filtros::rango($meses);

            $mesesDeDiferencia = (((int) substr($hasta, 0, 4) * 12) + (int) substr($hasta, 5, 2))
                - (((int) substr($desde, 0, 4) * 12) + (int) substr($desde, 5, 2));
            self::assertSame($meses, $mesesDeDiferencia, "rango({$meses}) tiene que abarcar {$meses} meses exactos");
        }
    }
}
