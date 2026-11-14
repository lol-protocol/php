<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Repositories\FlujoDeCajaRepository;
use DateTimeImmutable;

/** La pantalla del flujo de caja esperado y su CSV contra la app levantada. Solo lee: no deja nada que borrar. */
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

    public function testLaDescargaEsUnCsvAdjuntoSinCacheConUnaFilaPorCubeta(): void
    {
        $respuesta = $this->get('page=flujo-csv');

        $this->assertStatus(200, $respuesta);
        self::assertMatchesRegularExpression('/^Content-Type: text\/csv; charset=utf-8\r?$/mi', $respuesta['cabeceras']);
        self::assertMatchesRegularExpression('/^Content-Disposition: attachment; filename="flujo-de-caja-' . date('Y-m-d') . '\.csv"\r?$/mi', $respuesta['cabeceras']);
        self::assertMatchesRegularExpression('/^Cache-Control: no-store\r?$/mi', $respuesta['cabeceras']);

        $filas = self::leer($respuesta['cuerpo']);
        self::assertSame(['Vencimiento', 'Saldo USD', 'Boletas', 'Clientes'], $filas[0]);
        $primerMes = new DateTimeImmutable('first day of this month');
        self::assertSame(
            ['Ya vencido', ...array_map(static fn (int $i): string => $primerMes->modify("+{$i} months")->format('Y-m'), range(0, 5)), 'Más adelante'],
            array_column(array_slice($filas, 1), 0),
            'vencido, los seis meses desde este (en ISO, que una planilla ordena) y mas adelante'
        );
        foreach (array_slice($filas, 1) as $fila) {
            self::assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', (string) $fila[1], 'el saldo es un numero con los dos decimales del dolar');
            self::assertMatchesRegularExpression('/^\d+$/', (string) $fila[2]);
            self::assertMatchesRegularExpression('/^\d+$/', (string) $fila[3]);
        }
    }

    public function testLaSumaDelCsvEsLaCarteraPendiente(): void
    {
        $filas = array_slice(self::leer($this->get('page=flujo-csv')['cuerpo']), 1);
        $esperado = FlujoDeCajaRepository::totales((new FlujoDeCajaRepository())->cubetas(6))['total_usd'];

        self::assertEqualsWithDelta($esperado, array_sum(array_map(static fn (array $f): float => (float) $f[1], $filas)), 0.05, 'ocho filas redondeadas a centavos');
    }

    public function testElCsvRespetaElHorizonteYUnoQueNoExisteCaeEnElPorDefecto(): void
    {
        $cuantas = fn (string $query): int => count(self::leer($this->get($query)['cuerpo'])) - 1;

        self::assertSame(5, $cuantas('page=flujo-csv&meses=3'), 'vencido + 3 meses + mas adelante');
        self::assertSame(14, $cuantas('page=flujo-csv&meses=12'));
        self::assertSame(8, $cuantas('page=flujo-csv&meses=7'), 'un horizonte que no existe usa el de por defecto, sin error');
        self::assertSame(8, $cuantas('page=flujo-csv&meses[]=3'), 'un parametro repetido se descarta');
    }

    public function testLaPantallaOfreceElCsvConElHorizonteElegido(): void
    {
        $cuerpo = $this->get('page=flujo&meses=12')['cuerpo'];

        self::assertMatchesRegularExpression('~<a href="\?page=flujo-csv&amp;meses=12">Exportar CSV</a>~', $cuerpo);
    }
}
