<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FuncionesDeVistaTest extends TestCase
{
    public function testMesLabelConvierteFormatoIsoAEspanolCorto(): void
    {
        self::assertSame('mar 2026', etiqueta_de_mes('2026-03'));
        self::assertSame('dic 2025', etiqueta_de_mes('2025-12'));
    }

    public function testPctAlturaEscalaContraElMaximo(): void
    {
        self::assertSame(50.0, altura_en_pct(50.0, 100.0));
        self::assertSame(100.0, altura_en_pct(100.0, 100.0));
    }

    public function testPctAlturaDaCeroCuandoNoHayValorOMaximo(): void
    {
        self::assertSame(0.0, altura_en_pct(0.0, 100.0));
        self::assertSame(0.0, altura_en_pct(50.0, 0.0));
    }

    public function testPctAlturaTienePisoVisibleParaValoresChicos(): void
    {
        // Un valor > 0 nunca debe desaparecer del grafico por redondear a 0%.
        $resultado = altura_en_pct(1.0, 100_000.0);
        self::assertGreaterThanOrEqual(2.0, $resultado);
    }

    public function testMoneyCompactaUsaSufijosParaMilesYMillones(): void
    {
        self::assertSame('$12.3K', dinero_compacto(12345.0));
        self::assertSame('$4.2M', dinero_compacto(4_200_000.0));
        self::assertSame('$999.00', dinero_compacto(999.0));
    }

    /** dinero_compacto() se comia el signo en los tramos K/M: -4.2M se veia igual que +4.2M. */
    public function testMoneyCompactaConservaElSignoDeLosNegativos(): void
    {
        self::assertSame('-$12.3K', dinero_compacto(-12345.0));
        self::assertSame('-$4.2M', dinero_compacto(-4_200_000.0));
        self::assertSame('-$999.00', dinero_compacto(-999.0));
    }

    /** El signo va antes del simbolo: los cobros netos pueden dar negativo si hay devoluciones. */
    public function testMoneyPoneElSignoAntesDelSimbolo(): void
    {
        self::assertSame('-$58.00', \App\Config::dinero(-58.0));
        self::assertSame('$58.00', \App\Config::dinero(58.0));
    }

    public function testDeltaPctNuloSinBaseDeComparacion(): void
    {
        self::assertNull(variacion_pct(100.0, 0.0));
    }

    public function testDeltaPctCalculaVariacionRelativa(): void
    {
        self::assertEqualsWithDelta(10.0, variacion_pct(110.0, 100.0), 0.001);
        self::assertEqualsWithDelta(-25.0, variacion_pct(75.0, 100.0), 0.001);
    }

    public function testDeltaBadgeColoreaSegunSiSubirEsBueno(): void
    {
        self::assertStringContainsString('good', insignia_de_variacion(10.0, subirEsBueno: true));
        self::assertStringContainsString('critical', insignia_de_variacion(10.0, subirEsBueno: false));
        self::assertStringContainsString('critical', insignia_de_variacion(-10.0, subirEsBueno: true));
    }

    public function testColorCeldaCohorteEsMonotonoConLaIntensidad(): void
    {
        [$fondoBajo] = color_celda_cohorte(10.0);
        [$fondoAlto] = color_celda_cohorte(90.0);

        self::assertNotSame($fondoBajo, $fondoAlto);
        self::assertSame(['var(--gridline)', 'var(--text-muted)'], color_celda_cohorte(0.0));
    }

    public function testDeltaBadgeUsaLaEtiquetaProvista(): void
    {
        self::assertStringContainsString('vs. año anterior', insignia_de_variacion(5.0, true, 'vs. año anterior'));
        self::assertStringContainsString('Sin datos para comparar (vs. año anterior)', insignia_de_variacion(null, true, 'vs. año anterior'));
    }

    public function testDeltaBadgeUsaEtiquetaPorDefectoSiNoSeIndicaOtra(): void
    {
        self::assertStringContainsString('vs. período anterior', insignia_de_variacion(5.0));
    }

    public function testUrlConParametroPreservaLosDemasParametrosDeLaQuery(): void
    {
        $_GET = ['page' => 'cobros', 'meses' => '6'];

        $url = url_con_parametro('pagina', 2);

        self::assertStringStartsWith('?', $url);
        parse_str(ltrim($url, '?'), $params);
        self::assertSame(['page' => 'cobros', 'meses' => '6', 'pagina' => '2'], $params);
    }

    public function testUrlConParametroSobreescribeUnParametroExistente(): void
    {
        $_GET = ['page' => 'cobros', 'pagina' => '3'];

        $url = url_con_parametro('pagina', 5);

        parse_str(ltrim($url, '?'), $params);
        self::assertSame('5', $params['pagina']);
    }

    public function testUrlConParametrosCambiaVariosYSacaLosQueValenNull(): void
    {
        $_GET = ['page' => 'auditoria', 'antes' => '2026-01-01 10:00:00,5', 'pagina' => '3'];

        $url = url_con_parametros(['despues' => '2026-01-01 11:00:00,9', 'antes' => null, 'pagina' => null]);

        parse_str(ltrim($url, '?'), $params);
        self::assertSame(['page' => 'auditoria', 'despues' => '2026-01-01 11:00:00,9'], $params);
    }

    public function testUrlConParametrosSinCambiosDejaLaQueryIgual(): void
    {
        $_GET = ['page' => 'cobros', 'meses' => '6'];

        parse_str(ltrim(url_con_parametros([]), '?'), $params);

        self::assertSame(['page' => 'cobros', 'meses' => '6'], $params);
    }

    public function testSvgBarraIncluyeClaseEstiloColorYTooltip(): void
    {
        $svg = barra_svg('hbar-fill', 'width:50%', 'var(--series-1)', 'Enero: $100');

        self::assertStringContainsString('class="hbar-fill"', $svg);
        self::assertStringContainsString('style="width:50%"', $svg);
        self::assertStringContainsString('fill="var(--series-1)"', $svg);
        self::assertStringContainsString('aria-label="Enero: $100"', $svg);
        self::assertStringContainsString('<title>Enero: $100</title>', $svg);
    }

    public function testSvgBarraEscapaElTooltip(): void
    {
        $svg = barra_svg('bar', 'height:10%', 'red', 'Cliente "VIP" & socio');

        self::assertStringContainsString('Cliente &quot;VIP&quot; &amp; socio', $svg);
    }

    public function testSvgBarraUsaElRadioIndicado(): void
    {
        $svg = barra_svg('bar', 'width:10%', 'blue', 'x', 3);

        self::assertStringContainsString('rx="3"', $svg);
        self::assertStringContainsString('ry="3"', $svg);
    }

    public function testSvgBarraRadioPorDefectoEsCuatro(): void
    {
        $svg = barra_svg('bar', 'width:10%', 'blue', 'x');

        self::assertStringContainsString('rx="4"', $svg);
        self::assertStringContainsString('ry="4"', $svg);
    }

    public function testDeltaBadgeUsaLaUnidadIndicada(): void
    {
        self::assertStringContainsString('+10.0 pp vs. período anterior', insignia_de_variacion(10.0, etiqueta: 'vs. período anterior', unidad: ' pp'));
    }

    public function testDeltaBadgeUsaPorcentajePorDefecto(): void
    {
        self::assertStringContainsString('+10.0%', insignia_de_variacion(10.0));
    }
}
