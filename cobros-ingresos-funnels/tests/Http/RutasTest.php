<?php

declare(strict_types=1);

namespace App\Tests\Http;

/** Router, login obligatorio y robustez ante URLs raras, contra la app levantada. */
final class RutasTest extends HttpTestCase
{
    public function testSinSesionUnaPantallaRedirigeAlLogin(): void
    {
        $respuesta = $this->get('page=dashboard');

        $this->assertStatus(302, $respuesta);
        self::assertStringContainsString('page=login', (string) $respuesta['location']);
    }

    public function testConSesionTodasLasPantallasResponden(): void
    {
        $this->iniciarSesion();

        foreach (['dashboard', 'cobros', 'pagos', 'clientes', 'funnel', 'cohortes', 'auditoria', 'usuarios',
                  'boleta-nueva', 'pago-nuevo', 'cliente-nuevo', 'usuario-nuevo', 'cliente&id=1'] as $pagina) {
            $this->assertStatus(200, $this->get("page={$pagina}"), "page={$pagina}");
        }
    }

    public function testUnaPaginaQueNoExisteDa404(): void
    {
        $this->assertStatus(404, $this->get('page=no-existe'));
    }

    public function testUnPostSinTokenCsrfDa403(): void
    {
        $this->iniciarSesion();

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
        $this->iniciarSesion();

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
        $this->iniciarSesion();

        // Rango amplio para que haya varias paginas sin depender de las fechas del seed.
        $cuerpo = $this->get('page=cobros&desde=2000-01-01&hasta=2100-12-31&pagina=5000')['cuerpo'];

        self::assertMatchesRegularExpression('/Página (\d+) de \1\b/u', $cuerpo, 'tiene que quedar parado en la ultima pagina, no en la 5000');
    }
}
