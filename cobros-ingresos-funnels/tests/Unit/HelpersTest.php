<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testMesLabelConvierteFormatoIsoAEspanolCorto(): void
    {
        self::assertSame('mar 2026', mes_label('2026-03'));
        self::assertSame('dic 2025', mes_label('2025-12'));
    }

    public function testPctAlturaEscalaContraElMaximo(): void
    {
        self::assertSame(50.0, pct_altura(50.0, 100.0));
        self::assertSame(100.0, pct_altura(100.0, 100.0));
    }

    public function testPctAlturaDaCeroCuandoNoHayValorOMaximo(): void
    {
        self::assertSame(0.0, pct_altura(0.0, 100.0));
        self::assertSame(0.0, pct_altura(50.0, 0.0));
    }

    public function testPctAlturaTienePisoVisibleParaValoresChicos(): void
    {
        // Un valor > 0 nunca debe desaparecer del grafico por redondear a 0%.
        $resultado = pct_altura(1.0, 100_000.0);
        self::assertGreaterThanOrEqual(2.0, $resultado);
    }

    public function testMoneyCompactaUsaSufijosParaMilesYMillones(): void
    {
        self::assertSame('$12.3K', money_compacta(12345.0));
        self::assertSame('$4.2M', money_compacta(4_200_000.0));
        self::assertSame('$999.00', money_compacta(999.0));
    }

    public function testDeltaPctNuloSinBaseDeComparacion(): void
    {
        self::assertNull(delta_pct(100.0, 0.0));
    }

    public function testDeltaPctCalculaVariacionRelativa(): void
    {
        self::assertEqualsWithDelta(10.0, delta_pct(110.0, 100.0), 0.001);
        self::assertEqualsWithDelta(-25.0, delta_pct(75.0, 100.0), 0.001);
    }

    public function testDeltaBadgeColoreaSegunSiSubirEsBueno(): void
    {
        self::assertStringContainsString('good', delta_badge(10.0, subirEsBueno: true));
        self::assertStringContainsString('critical', delta_badge(10.0, subirEsBueno: false));
        self::assertStringContainsString('critical', delta_badge(-10.0, subirEsBueno: true));
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
        self::assertStringContainsString('vs. año anterior', delta_badge(5.0, true, 'vs. año anterior'));
        self::assertStringContainsString('Sin datos para comparar (vs. año anterior)', delta_badge(null, true, 'vs. año anterior'));
    }

    public function testDeltaBadgeUsaEtiquetaPorDefectoSiNoSeIndicaOtra(): void
    {
        self::assertStringContainsString('vs. periodo anterior', delta_badge(5.0));
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

    public function testSvgBarraIncluyeClaseEstiloColorYTooltip(): void
    {
        $svg = svg_barra('hbar-fill', 'width:50%', 'var(--series-1)', 'Enero: $100');

        self::assertStringContainsString('class="hbar-fill"', $svg);
        self::assertStringContainsString('style="width:50%"', $svg);
        self::assertStringContainsString('fill="var(--series-1)"', $svg);
        self::assertStringContainsString('aria-label="Enero: $100"', $svg);
        self::assertStringContainsString('<title>Enero: $100</title>', $svg);
    }

    public function testSvgBarraEscapaElTooltip(): void
    {
        $svg = svg_barra('bar', 'height:10%', 'red', 'Cliente "VIP" & socio');

        self::assertStringContainsString('Cliente &quot;VIP&quot; &amp; socio', $svg);
    }

    public function testSvgBarraUsaElRadioIndicado(): void
    {
        $svg = svg_barra('bar', 'width:10%', 'blue', 'x', 3);

        self::assertStringContainsString('rx="3"', $svg);
        self::assertStringContainsString('ry="3"', $svg);
    }

    public function testSvgBarraRadioPorDefectoEsCuatro(): void
    {
        $svg = svg_barra('bar', 'width:10%', 'blue', 'x');

        self::assertStringContainsString('rx="4"', $svg);
        self::assertStringContainsString('ry="4"', $svg);
    }

    public function testDeltaBadgeUsaLaUnidadIndicada(): void
    {
        self::assertStringContainsString('+10.0 pp vs. periodo anterior', delta_badge(10.0, etiqueta: 'vs. periodo anterior', unidad: ' pp'));
    }

    public function testDeltaBadgeUsaPorcentajePorDefecto(): void
    {
        self::assertStringContainsString('+10.0%', delta_badge(10.0));
    }
}
