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
            'funnel' => 'Funnel de conversión',
            'cohortes' => 'Cohortes de conversión',
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

    /**
     * Con un rango amplio, cada estado elegido responde y entre los cinco se
     * reparten todas las boletas del rango: lo que dice "Boletas (N)" con cada
     * filtro suma el N sin filtro. Es el recorrido completo (URL, controlador,
     * filtro en SQL, vista). Antes ese filtro traia todo el rango a PHP, y con
     * unas 100 mil boletas agotaba la memoria y la pagina salia en blanco; eso lo
     * mide RendimientoAEscalaTest, porque el seed tiene demasiado pocas filas.
     */
    public function testLosCincoFiltrosDeEstadoSeRepartenTodasLasBoletasDelRango(): void
    {
        $rango = 'desde=2000-01-01&hasta=2100-12-31';
        $totalDeBoletas = function (string $consulta): int {
            $respuesta = $this->get($consulta);
            $this->assertStatus(200, $respuesta, $consulta);
            if (preg_match('~<h2>Boletas \((\d+)\)</h2>~', $respuesta['cuerpo'], $coincidencia) !== 1) {
                self::fail("{$consulta} no muestra el total de boletas");
            }

            return (int) $coincidencia[1];
        };

        $total = $totalDeBoletas("page=cobros&{$rango}");
        $suma = 0;
        foreach (['pagada', 'parcial', 'pendiente', 'vencida', 'anulada'] as $estado) {
            $suma += $totalDeBoletas("page=cobros&{$rango}&estado={$estado}");
        }

        self::assertGreaterThan(0, $total);
        self::assertSame($total, $suma, 'los cinco estados tienen que repartirse todas las boletas del rango');
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

    /**
     * El desplegable de clientes se corta en un limite, y el unico camino a
     * los demas es la ficha: tiene que ofrecer cargar la boleta o el pago con
     * el cliente ya elegido, y los dos formularios tienen que respetarlo.
     */
    public function testLaFichaOfreceCargarBoletaYPagoConElClienteYaElegido(): void
    {
        $ficha = $this->get('page=cliente&id=1')['cuerpo'];
        self::assertStringContainsString('href="?page=boleta-nueva&amp;cliente_id=1"', $ficha);
        self::assertStringContainsString('href="?page=pago-nuevo&amp;cliente_id=1"', $ficha);

        $boleta = $this->get('page=boleta-nueva&cliente_id=1');
        $this->assertStatus(200, $boleta);
        self::assertMatchesRegularExpression('/<option value="1"\s+selected/', $boleta['cuerpo'], 'la boleta nueva ya trae al cliente elegido');

        $pago = $this->get('page=pago-nuevo&cliente_id=1');
        $this->assertStatus(200, $pago);
        self::assertStringContainsString('cambiar cliente', $pago['cuerpo'], 'el pago nuevo salta directo al formulario del cliente');
    }

    /**
     * Los topes de largo y las sugerencias de idioma tienen que llegar al
     * navegador: el servidor valida, pero el campo tiene que avisar antes.
     */
    public function testLosFormulariosLimitanLosTextosYElDeClienteSugiereLosIdiomasEnUso(): void
    {
        $cliente = $this->get('page=cliente-nuevo')['cuerpo'];
        foreach (['nombre' => 120, 'email' => 254, 'ciudad' => 100, 'idioma' => 40] as $campo => $maximo) {
            self::assertMatchesRegularExpression('/name="' . $campo . '"[^>]*maxlength="' . $maximo . '"/', $cliente, $campo);
        }
        self::assertMatchesRegularExpression('/name="idioma"[^>]*list="idiomas"/', $cliente);
        self::assertMatchesRegularExpression('~<datalist id="idiomas">.*<option value="Espanol">.*</datalist>~s', $cliente, 'sugiere los idiomas que ya tienen clientes (el seed deja Espanol)');

        self::assertMatchesRegularExpression('/name="concepto"[^>]*maxlength="200"/', $this->get('page=boleta-nueva')['cuerpo']);
    }
}
