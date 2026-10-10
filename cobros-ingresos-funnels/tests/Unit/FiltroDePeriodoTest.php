<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Avisos;
use App\FiltroDePeriodo;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FiltroDePeriodoTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function testMesesSoloAceptaValoresPermitidos(): void
    {
        $_GET['meses'] = '12';
        self::assertSame(12, FiltroDePeriodo::meses());

        $_GET['meses'] = '999';
        self::assertSame(6, FiltroDePeriodo::meses(), 'un valor no permitido cae al default de 6');

        unset($_GET['meses']);
        self::assertSame(6, FiltroDePeriodo::meses(), 'sin parametro tambien cae al default de 6');
    }

    public function testRangoTerminaHoyYEmpiezaNMesesAntes(): void
    {
        [$desde, $hasta] = FiltroDePeriodo::rango(3);

        self::assertSame(date('Y-m-d'), $hasta);
        self::assertSame(
            FiltroDePeriodo::restarMeses(new DateTimeImmutable('today'), 3)->format('Y-m-d'),
            $desde
        );
    }

    public function testRangoAnteriorEsInmediatamenteAnteriorYDelMismoLargo(): void
    {
        [$desdeAnterior, $hastaAnterior] = FiltroDePeriodo::rangoAnterior('2026-06-01', '2026-09-01');

        self::assertSame('2026-05-31', $hastaAnterior, 'termina el dia justo antes de que empiece el periodo actual');
        self::assertSame('2026-02-28', $desdeAnterior, 'mismo largo (92 dias) que el periodo actual');
    }

    public function testRangoAnteriorNoSuperponeConElRangoActual(): void
    {
        [$desde, $hasta] = FiltroDePeriodo::rango(6);
        [, $hastaAnterior] = FiltroDePeriodo::rangoAnterior($desde, $hasta);

        self::assertLessThan($desde, $hastaAnterior);
    }

    public function testRangoAnioAnteriorRestaExactamenteUnAnioCalendario(): void
    {
        [$desde, $hasta] = FiltroDePeriodo::rangoAnioAnterior('2026-06-01', '2026-09-01');

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
        [$desde] = FiltroDePeriodo::rangoAnioAnterior('2024-02-29', '2024-03-01');

        self::assertSame('2023-02-28', $desde);
    }

    public function testRangoPersonalizadoNuloSinParametros(): void
    {
        unset($_GET['desde'], $_GET['hasta']);
        self::assertNull(FiltroDePeriodo::rangoPersonalizado());
    }

    public function testRangoPersonalizadoNuloConFechaInvalida(): void
    {
        $_GET['desde'] = '2026-13-40';
        $_GET['hasta'] = '2026-09-01';
        self::assertNull(FiltroDePeriodo::rangoPersonalizado());
    }

    public function testRangoPersonalizadoNuloCuandoDesdeEsPosteriorAHasta(): void
    {
        $_GET['desde'] = '2026-09-01';
        $_GET['hasta'] = '2026-06-01';
        self::assertNull(FiltroDePeriodo::rangoPersonalizado());

        unset($_GET['desde'], $_GET['hasta']);
    }

    public function testRangoPersonalizadoValidoDevuelveLasMismasFechas(): void
    {
        $_GET['desde'] = '2026-01-15';
        $_GET['hasta'] = '2026-02-20';

        self::assertSame(['2026-01-15', '2026-02-20'], FiltroDePeriodo::rangoPersonalizado());

        unset($_GET['desde'], $_GET['hasta']);
    }

    public function testRangoActivoUsaElPersonalizadoCuandoEstaPresente(): void
    {
        $_GET['desde'] = '2026-01-15';
        $_GET['hasta'] = '2026-02-20';

        $contexto = FiltroDePeriodo::rangoActivo();

        self::assertSame('2026-01-15', $contexto['desde']);
        self::assertSame('2026-02-20', $contexto['hasta']);
        self::assertTrue($contexto['personalizado']);

        unset($_GET['desde'], $_GET['hasta']);
    }

    public function testRangoActivoCaeAlRangoPorMesesSinPersonalizado(): void
    {
        unset($_GET['desde'], $_GET['hasta']);
        $_GET['meses'] = '3';

        $contexto = FiltroDePeriodo::rangoActivo();

        self::assertSame(3, $contexto['meses']);
        self::assertSame(FiltroDePeriodo::rango(3), [$contexto['desde'], $contexto['hasta']]);
        self::assertFalse($contexto['personalizado']);

        unset($_GET['meses']);
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
                FiltroDePeriodo::restarMeses(new DateTimeImmutable($hoy), $meses)->format('Y-m-d'),
                "{$hoy} menos {$meses} meses"
            );
        }
    }

    public function testRestarMesesDejaIntactaUnaFechaQueSiExisteEnElMesDestino(): void
    {
        self::assertSame('2026-03-20', FiltroDePeriodo::restarMeses(new DateTimeImmutable('2026-09-20'), 6)->format('Y-m-d'));
        self::assertSame('2025-09-20', FiltroDePeriodo::restarMeses(new DateTimeImmutable('2026-09-20'), 12)->format('Y-m-d'));
    }

    /** El desde de rango() nunca puede caer en un mes posterior al que corresponde. */
    public function testRangoNoAdelantaElMesDeInicio(): void
    {
        foreach ([3, 6, 12] as $meses) {
            [$desde, $hasta] = FiltroDePeriodo::rango($meses);

            $mesesDeDiferencia = (((int) substr($hasta, 0, 4) * 12) + (int) substr($hasta, 5, 2))
                - (((int) substr($desde, 0, 4) * 12) + (int) substr($desde, 5, 2));
            self::assertSame($meses, $mesesDeDiferencia, "rango({$meses}) tiene que abarcar {$meses} meses exactos");
        }
    }

    /** Una sola lista de periodos: la que valida meses() es la misma que dibuja el selector de las cinco pantallas. */
    public function testLasOpcionesDeMesesSonLaUnicaFuenteDeLosValoresValidos(): void
    {
        self::assertArrayHasKey(FiltroDePeriodo::MESES_POR_DEFECTO, FiltroDePeriodo::OPCIONES_MESES, 'el default tiene que ser una opcion que exista');

        foreach (range(0, 24) as $meses) {
            $_GET['meses'] = (string) $meses;
            $esperado = array_key_exists($meses, FiltroDePeriodo::OPCIONES_MESES) ? $meses : FiltroDePeriodo::MESES_POR_DEFECTO;

            self::assertSame($esperado, FiltroDePeriodo::meses(), "meses={$meses}");
        }
    }

    /** Antes meses=7 volvia a 6 sin decir nada: quien lo habia pedido veia otro periodo creyendo ver el suyo. */
    public function testUnPeriodoQueNoExisteSeAvisaYCaeAlDefault(): void
    {
        foreach (['7', '999', 'abc', '6abc', '-3', '3.5'] as $pedido) {
            $_GET['meses'] = $pedido;

            self::assertSame(FiltroDePeriodo::MESES_POR_DEFECTO, FiltroDePeriodo::meses(), "meses={$pedido}");
            self::assertSame(
                ['El período pedido no es válido; se muestran los últimos 6 meses.'],
                array_column(FiltroDePeriodo::avisos(), 'texto'),
                "meses={$pedido}"
            );
        }
    }

    public function testUnPeriodoValidoOAusenteNoAvisaNada(): void
    {
        foreach ([null, '', ' ', '3', '6', '12', '06'] as $pedido) {
            unset($_GET['meses']);
            if ($pedido !== null) {
                $_GET['meses'] = $pedido;
            }

            self::assertSame([], FiltroDePeriodo::avisos(), var_export($pedido, true));
        }
    }

    /**
     * Un rango que no se puede usar (incompleto, con una fecha imposible o al
     * reves) se explica, y lo tipeado vuelve al formulario para corregirlo:
     * antes se descartaba en silencio y los campos quedaban vacios.
     */
    public function testUnRangoQueSeIgnoraSeExplicaYConservaLoTipeado(): void
    {
        $casos = [
            'solo desde' => [['desde' => '2026-01-01'], 'Para usar un rango exacto completá «Desde» y «Hasta»'],
            'solo hasta' => [['hasta' => '2026-01-01'], 'Para usar un rango exacto completá «Desde» y «Hasta»'],
            'texto en vez de fecha' => [['desde' => 'ayer', 'hasta' => '2026-01-01'], '«Desde» y «Hasta» tienen que ser fechas válidas'],
            'fecha imposible' => [['desde' => '2026-02-30', 'hasta' => '2026-03-01'], '«Desde» y «Hasta» tienen que ser fechas válidas'],
            'al reves' => [['desde' => '2026-05-01', 'hasta' => '2026-01-01'], '«Desde» no puede ser posterior a «Hasta»'],
        ];

        foreach ($casos as $nombre => [$get, $motivo]) {
            $_GET = $get;
            $activo = FiltroDePeriodo::rangoActivo();

            self::assertFalse($activo['personalizado'], $nombre);
            self::assertSame([$motivo . '; mientras tanto se muestra el período elegido.'], array_column($activo['avisos'], 'texto'), $nombre);
            self::assertSame($get['desde'] ?? '', $activo['desdeIngresado'], "{$nombre}: no borra lo tipeado en Desde");
            self::assertSame($get['hasta'] ?? '', $activo['hastaIngresado'], "{$nombre}: no borra lo tipeado en Hasta");
        }
    }

    public function testUnRangoValidoOVacioNoAvisaNada(): void
    {
        foreach ([[], ['desde' => '', 'hasta' => ''], ['desde' => '2026-01-01', 'hasta' => '2026-01-01'], ['desde' => '2026-01-01', 'hasta' => '2026-02-20']] as $get) {
            $_GET = $get;

            self::assertSame([], FiltroDePeriodo::avisos(), (string) json_encode($get));
        }
    }

    public function testLosAvisosDeFiltroSonDeTipoAtencion(): void
    {
        $_GET['meses'] = '7';

        self::assertSame(Avisos::ATENCION, FiltroDePeriodo::avisos()[0]['tipo']);
    }
}
