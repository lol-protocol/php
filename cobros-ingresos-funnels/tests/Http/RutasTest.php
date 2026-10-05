<?php

declare(strict_types=1);

namespace App\Tests\Http;

/** Router y robustez ante URLs raras, contra la app levantada. */
final class RutasTest extends HttpTestCase
{
    public function testTodasLasPantallasResponden(): void
    {
        foreach (['dashboard', 'cobros', 'pagos', 'clientes', 'funnel', 'cohortes', 'auditoria',
                  'boleta-nueva', 'pago-nuevo', 'cliente-nuevo', 'cliente&id=1'] as $pagina) {
            $this->assertStatus(200, $this->get("page={$pagina}"), "page={$pagina}");
        }
    }

    public function testUnaPaginaQueNoExisteDa404(): void
    {
        $this->assertStatus(404, $this->get('page=no-existe'));
    }

    public function testUnPostSinTokenCsrfDa403(): void
    {
        $this->assertStatus(403, $this->post('page=boleta-nueva', ['concepto' => 'sin token']));
    }

    /**
     * Regresion de una ronda anterior: cada una de estas URLs daba un 500
     * (parametros de tipo array que reventaban una firma tipada, y paginas
     * absurdas que desbordaban el offset). Probarlo aca y no solo en los
     * helpers cubre tambien el cableado: si alguien saca
     * Peticion::normalizarParametros() de public/index.php, esto falla.
     */
    public function testParametrosRepetidosOPaginasAbsurdasNoRompenNinguna(): void
    {
        foreach ([
            'page[]=dashboard',
            'page=cobros&estado[]=pendiente',
            'page=cobros&pagina=9999999999999999999',
            'page=cobros&estado=pagada&pagina=9999999999999999999',
            'page=pagos&pagina=9999999999999999999',
            'page=clientes&pagina=9999999999999999999',
            'page=auditoria&pagina=9999999999999999999',
        ] as $query) {
            $this->assertStatus(200, $this->get($query), $query);
        }
    }

    public function testUnaPaginaFueraDeRangoMuestraLaUltimaReal(): void
    {
        // Rango amplio para que haya varias paginas sin depender de las fechas del seed.
        $cuerpo = $this->get('page=cobros&desde=2000-01-01&hasta=2100-12-31&pagina=5000')['cuerpo'];

        self::assertMatchesRegularExpression('/Página (\d+) de \1\b/u', $cuerpo, 'tiene que quedar parado en la ultima pagina, no en la 5000');
    }

    /**
     * Regresion: View::render le pasaba al layout el scope entero de la vista, y
     * las dos pantallas que reasignan $titulo (el foreach de segmentacion del
     * Dashboard y las tablas de conversion del Funnel) terminaban con el <title>
     * de una de sus secciones ("Rango de edad", "Conversion por rango de edad").
     */
    public function testElTituloDeLaPestanaEsElDeLaPantalla(): void
    {
        foreach ([
            'dashboard' => 'Dashboard',
            'cobros' => 'Cobros e ingresos',
            'pagos' => 'Pagos',
            'funnel' => 'Funnel de conversion',
            'cohortes' => 'Cohortes de conversion',
            'clientes' => 'Clientes',
            'auditoria' => 'Auditoría',
        ] as $pagina => $titulo) {
            $cuerpo = $this->get("page={$pagina}")['cuerpo'];

            // El primer <title> es el de la pagina; los siguientes son tooltips de los graficos SVG.
            if (preg_match('~<title>([^<]*)</title>~', $cuerpo, $coincidencia) !== 1) {
                self::fail("page={$pagina} no tiene <title>");
            }
            self::assertStringStartsWith($titulo . ' · ', html_entity_decode($coincidencia[1]), "page={$pagina}");
        }
    }

    /**
     * Regresion: cada filtro invalido se resolvia a su manera y todos en
     * silencio (meses=7 volvia a 6, un estado inventado daba la tabla vacia con
     * el selector en "Todos", un rango al reves se ignoraba y borraba lo
     * tipeado). Ahora se usa el valor por defecto y se avisa en pantalla.
     */
    public function testUnPeriodoQueNoExisteSeAvisaEnLasCincoPantallasConFiltro(): void
    {
        foreach (['dashboard', 'cobros', 'pagos', 'funnel', 'cohortes'] as $pagina) {
            $cuerpo = $this->get("page={$pagina}&meses=7")['cuerpo'];

            self::assertStringContainsString('El período pedido no es válido; se muestran los últimos 6 meses.', $cuerpo, $pagina);
            self::assertMatchesRegularExpression('/<option value="6"\s+selected/', $cuerpo, "{$pagina}: queda el periodo por defecto");
        }
    }

    public function testUnRangoAlRevesOIncompletoSeAvisaYConservaLoTipeado(): void
    {
        foreach (['dashboard', 'cobros', 'pagos', 'funnel', 'cohortes'] as $pagina) {
            $alReves = $this->get("page={$pagina}&desde=2026-05-01&hasta=2026-01-01")['cuerpo'];
            self::assertStringContainsString('«Desde» no puede ser posterior a «Hasta»', $alReves, $pagina);
            self::assertMatchesRegularExpression('/name="desde"[^>]*value="2026-05-01"/', $alReves, "{$pagina}: conserva Desde");
            self::assertMatchesRegularExpression('/name="hasta"[^>]*value="2026-01-01"/', $alReves, "{$pagina}: conserva Hasta");

            $incompleto = $this->get("page={$pagina}&desde=2026-01-01")['cuerpo'];
            self::assertStringContainsString('Para usar un rango exacto completá «Desde» y «Hasta»', $incompleto, $pagina);
            self::assertMatchesRegularExpression('/name="desde"[^>]*value="2026-01-01"/', $incompleto, "{$pagina}: conserva Desde");
        }
    }

    public function testUnEstadoQueNoExisteSeAvisaYMuestraTodasLasBoletas(): void
    {
        $rango = 'desde=2000-01-01&hasta=2100-12-31';
        $sinFiltro = $this->get("page=cobros&{$rango}")['cuerpo'];
        $inventado = $this->get("page=cobros&{$rango}&estado=inventado")['cuerpo'];

        self::assertStringContainsString('El estado pedido no existe; se muestran las boletas de todos los estados.', $inventado);
        self::assertStringNotContainsString('No hay boletas para este filtro.', $inventado, 'antes daba la tabla vacia');
        self::assertSame(substr_count($sinFiltro, '<tr>'), substr_count($inventado, '<tr>'), 'las mismas filas que sin filtrar');
        self::assertStringNotContainsString('El estado pedido no existe', $sinFiltro);
    }

    public function testUnFiltroValidoNoMuestraNingunAviso(): void
    {
        foreach (['page=dashboard&meses=12', 'page=cobros&estado=pagada&desde=2026-01-01&hasta=2026-02-01', 'page=pagos&meses=3'] as $query) {
            self::assertStringNotContainsString('class="aviso', $this->get($query)['cuerpo'], $query);
        }
    }
}
