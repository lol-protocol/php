<?php

declare(strict_types=1);

namespace App\Tests\Http;

/** La pantalla del flujo de caja esperado contra la app levantada. Solo lee: no deja nada que borrar. */
final class FlujoDeCajaTest extends HttpTestCase
{
    /** El valor del KPI con ese rotulo (el texto ya formateado, como lo ve la persona). */
    private static function kpi(string $cuerpo, string $rotulo): string
    {
        if (preg_match('~' . preg_quote($rotulo, '~') . '[^<]*</span>\s*<span class="value">([^<]*)</span>~u', $cuerpo, $m) !== 1) {
            self::fail("no hay un KPI «{$rotulo}»");
        }

        return $m[1];
    }

    public function testLaPantallaMuestraLasCubetasYSuTotalCuadraConElDashboard(): void
    {
        $flujo = $this->get('page=flujo');
        $this->assertStatus(200, $flujo);

        foreach (['Flujo de caja esperado', 'Ya vencido', 'Más adelante', 'Por mes de vencimiento'] as $texto) {
            self::assertStringContainsString($texto, $flujo['cuerpo']);
        }
        // Una fila por cubeta con el horizonte por defecto: vencido + 6 meses + mas adelante.
        self::assertSame(8, preg_match_all('~<td class="barra-celda">~', $flujo['cuerpo']));

        $dashboard = $this->get('page=dashboard')['cuerpo'];
        self::assertSame(
            self::kpi($dashboard, 'Cartera pendiente'),
            self::kpi($flujo['cuerpo'], 'Por cobrar (USD)'),
            'lo que falta cobrar en el flujo es la cartera pendiente del Dashboard'
        );
    }

    public function testElSelectorOfreceLosTresHorizontesYMarcaElElegido(): void
    {
        $cuerpo = $this->get('page=flujo&meses=12')['cuerpo'];

        self::assertStringContainsString('<option value="3" >', $cuerpo);
        self::assertStringContainsString('<option value="6" >', $cuerpo);
        self::assertStringContainsString('<option value="12" selected>', $cuerpo);
        self::assertSame(14, preg_match_all('~<td class="barra-celda">~', $cuerpo), 'vencido + 12 meses + mas adelante');
    }

    public function testUnHorizonteQueNoExisteAvisaYUsaElPorDefecto(): void
    {
        $respuesta = $this->get('page=flujo&meses=7');

        $this->assertStatus(200, $respuesta);
        self::assertStringContainsString('El horizonte pedido no existe; se muestran los próximos 6 meses.', $respuesta['cuerpo']);
        self::assertStringContainsString('<option value="6" selected>', $respuesta['cuerpo']);
        self::assertSame(8, preg_match_all('~<td class="barra-celda">~', $respuesta['cuerpo']));
    }

    public function testSinPedirHorizonteNoHayAviso(): void
    {
        self::assertStringNotContainsString('no existe', $this->get('page=flujo')['cuerpo']);
        self::assertStringNotContainsString('no existe', $this->get('page=flujo&meses=')['cuerpo'], 'vacio es no haber pedido nada');
    }

    public function testApareceEnLaBarraDePestanasYEstaActiva(): void
    {
        $cuerpo = $this->get('page=flujo')['cuerpo'];

        self::assertStringContainsString('<a href="?page=flujo" class="active">Flujo de caja</a>', $cuerpo);
        self::assertStringContainsString('<a href="?page=flujo" class="">Flujo de caja</a>', $this->get('page=dashboard')['cuerpo']);
    }
}
