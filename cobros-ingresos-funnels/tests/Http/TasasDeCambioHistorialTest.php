<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;
use App\Paginacion;

/**
 * La pantalla de tasas de cambio (?page=tasas): sin ?moneda=, el resumen de todas; con ella, el historial de
 * esa moneda. El servidor lee con su propia conexion, asi que lo que un caso escribe va commiteado y se deshace
 * al terminar: la tasa de la moneda como estaba y las filas de historial que dejo el trigger.
 */
final class TasasDeCambioHistorialTest extends HttpTestCase
{
    /** Deja la moneda y su historial como estaban al empezar el test. */
    private function restaurarAlTerminar(string $codigo): void
    {
        $db = Database::connection();
        $moneda = $db->prepare('SELECT tasa_a_usd, tasa_actualizada_en, tasa_fuente FROM monedas WHERE codigo = :c');
        $moneda->execute([':c' => $codigo]);
        $antes = $moneda->fetch();
        $ultimo = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM historial_de_tasas')->fetchColumn();

        $this->alTerminar(static function () use ($codigo, $antes, $ultimo): void {
            $db = Database::connection();
            $db->prepare('UPDATE monedas SET tasa_a_usd = :t, tasa_actualizada_en = :q, tasa_fuente = :f WHERE codigo = :c')
                ->execute([':t' => $antes['tasa_a_usd'], ':q' => $antes['tasa_actualizada_en'], ':f' => $antes['tasa_fuente'], ':c' => $codigo]);
            $db->prepare('DELETE FROM historial_de_tasas WHERE id > :ultimo')->execute([':ultimo' => $ultimo]);
        });
    }

    public function testElResumenListaLasMonedasConUnLinkASuHistorial(): void
    {
        $respuesta = $this->get('page=tasas');

        $this->assertStatus(200, $respuesta);
        self::assertStringContainsString('<h1>Tasas de cambio</h1>', $respuesta['cuerpo']);
        self::assertStringContainsString('<option value="CLP"', $respuesta['cuerpo']);
        self::assertStringContainsString('href="?page=tasas&amp;moneda=CLP"', $respuesta['cuerpo'], 'cada moneda del resumen lleva a su historial');
        self::assertStringNotContainsString('<option value="USD"', $respuesta['cuerpo'], 'el dolar es la base, no una moneda que seguir');
    }

    public function testLaPestanaDeTasasEstaEnLaNavegacion(): void
    {
        $cuerpo = $this->get('page=tasas')['cuerpo'];

        self::assertMatchesRegularExpression('#<a href="\?page=tasas" class="active">Tasas de cambio</a>#', $cuerpo);
    }

    public function testElHistorialDeUnaMonedaMuestraSusCambiosYCuantoCambiaron(): void
    {
        $this->restaurarAlTerminar('CLP');
        $db = Database::connection();
        $db->exec("UPDATE monedas SET tasa_a_usd = 0.001, tasa_actualizada_en = '2026-10-01 12:00:00+00', tasa_fuente = 'fuente-de-prueba' WHERE codigo = 'CLP'");
        $db->exec("UPDATE monedas SET tasa_a_usd = 0.0011, tasa_actualizada_en = '2026-10-02 12:00:00+00' WHERE codigo = 'CLP'");

        $respuesta = $this->get('page=tasas&moneda=CLP');

        $this->assertStatus(200, $respuesta);
        $cuerpo = $respuesta['cuerpo'];
        self::assertStringContainsString('<h1>Tasas de cambio · CLP</h1>', $cuerpo);
        self::assertStringContainsString('Peso chileno', $cuerpo, 'dice de que moneda es el historial');
        self::assertStringContainsString('+10.00%', $cuerpo, 'de 0.001 a 0.0011 la moneda subio 10 %');
        self::assertStringContainsString('fuente-de-prueba', $cuerpo);
        self::assertStringContainsString('2026-10-02 12:00', $cuerpo);
        self::assertStringContainsString('<option value="CLP" selected>', $cuerpo, 'el selector queda en la moneda que se mira');
        self::assertLessThan(strpos($cuerpo, '2026-10-01 12:00'), strpos($cuerpo, '2026-10-02 12:00'), 'la mas reciente primero');
    }

    public function testUnaMonedaQueNoExisteAvisaYMuestraElResumen(): void
    {
        foreach (['XXX', 'USD', '<script>alert(1)</script>'] as $pedida) {
            $respuesta = $this->get('page=tasas&moneda=' . rawurlencode($pedida));

            $this->assertStatus(200, $respuesta, $pedida);
            self::assertStringContainsString('La moneda pedida no existe; se muestran todas.', $respuesta['cuerpo'], $pedida);
            self::assertStringContainsString('<h1>Tasas de cambio</h1>', $respuesta['cuerpo'], $pedida);
            self::assertStringNotContainsString('<script>alert(1)', $respuesta['cuerpo'], $pedida);
        }
    }

    public function testUnaPaginaEnormeNoRompeElHistorial(): void
    {
        foreach (['99999999999999999999', '0', '-3', 'abc'] as $pagina) {
            $this->assertStatus(200, $this->get('page=tasas&moneda=CLP&pagina=' . $pagina), $pagina);
        }
    }

    public function testElCsvDelResumenTraeUnaFilaPorMonedaSinElDolar(): void
    {
        $respuesta = $this->get('page=tasas-csv');

        $this->assertStatus(200, $respuesta);
        self::assertMatchesRegularExpression('/^Content-Type: text\/csv; charset=utf-8\r?$/mi', $respuesta['cabeceras']);
        self::assertMatchesRegularExpression('/^Content-Disposition: attachment; filename="tasas-de-cambio-' . date('Y-m-d') . '\.csv"\r?$/mi', $respuesta['cabeceras']);
        self::assertMatchesRegularExpression('/^Cache-Control: no-store\r?$/mi', $respuesta['cabeceras']);

        $filas = self::leer($respuesta['cuerpo']);
        self::assertSame(['Moneda', 'Nombre', 'Valor en USD', '1 USD =', 'Cotización', 'Cambio en 30 días (%)'], $filas[0]);
        $monedas = (int) Database::connection()->query("SELECT COUNT(*) FROM monedas WHERE codigo <> 'USD'")->fetchColumn();
        self::assertCount($monedas, array_slice($filas, 1));
        $codigos = array_column(array_slice($filas, 1), 0);
        self::assertNotContains('USD', $codigos);
        self::assertContains('CLP', $codigos);
    }

    public function testElCsvDelHistorialTraeTodasLasFilasDeLaMonedaConSuCambio(): void
    {
        $this->restaurarAlTerminar('CLP');
        $db = Database::connection();
        $db->exec("UPDATE monedas SET tasa_a_usd = 0.001, tasa_actualizada_en = '2026-10-01 12:00:00+00', tasa_fuente = 'fuente-de-prueba' WHERE codigo = 'CLP'");
        $db->exec("UPDATE monedas SET tasa_a_usd = 0.0011, tasa_actualizada_en = '2026-10-02 12:00:00+00' WHERE codigo = 'CLP'");
        // Mas de una pagina de la pantalla (25): el CSV baja todas.
        $db->exec("INSERT INTO historial_de_tasas (moneda_codigo, tasa_a_usd, cotizada_en, fuente)
                   SELECT 'CLP', 0.002, timestamptz '2026-01-01 00:00+00' + n * interval '1 hour', 'relleno' FROM generate_series(1, 40) n");
        $enLaBase = (int) $db->query("SELECT COUNT(*) FROM historial_de_tasas WHERE moneda_codigo = 'CLP'")->fetchColumn();
        self::assertGreaterThan(Paginacion::POR_PAGINA, $enLaBase);

        $respuesta = $this->get('page=tasas-csv&moneda=CLP&pagina=2');

        $this->assertStatus(200, $respuesta);
        self::assertMatchesRegularExpression('/filename="tasas-de-cambio-CLP-' . date('Y-m-d') . '\.csv"/', $respuesta['cabeceras']);
        $filas = self::leer($respuesta['cuerpo']);
        self::assertSame(['Cotización', 'Valor en USD', '1 USD =', 'Cambio (%)', 'Fuente', 'Registrada'], $filas[0]);
        self::assertCount($enLaBase, array_slice($filas, 1), 'todas las filas de la moneda, no solo la pagina que se pidio');

        // Las dos filas que anotan los UPDATE de arriba: la del 2 de octubre (+10 % frente a la del 1) y la del 1 de octubre.
        $porFecha = [];
        foreach (array_slice($filas, 1) as $fila) {
            $porFecha[$fila[0]] = $fila;
        }
        self::assertSame(['2026-10-02 12:00', '0.00110000', '909.090909', '10.00', 'fuente-de-prueba'], array_slice($porFecha['2026-10-02 12:00'], 0, 5));
        self::assertSame('2026-10-01 12:00', $porFecha['2026-10-01 12:00'][0]);
        self::assertSame('0.00100000', $porFecha['2026-10-01 12:00'][1]);
        self::assertSame('1000.000000', $porFecha['2026-10-01 12:00'][2]);
        self::assertContains('de ejemplo', array_column(array_slice($filas, 1), 0), 'la fila de ejemplo de la migracion, escrita como en la pantalla');
    }

    public function testElCsvDeUnaMonedaQueNoExisteEsElResumen(): void
    {
        foreach (['XXX', 'USD'] as $pedida) {
            $filas = self::leer($this->get('page=tasas-csv&moneda=' . $pedida)['cuerpo']);

            self::assertSame('Moneda', $filas[0][0], $pedida);
        }
    }

    public function testLasDosPantallasOfrecenElCsv(): void
    {
        self::assertStringContainsString('<a href="?page=tasas-csv">Exportar CSV</a>', $this->get('page=tasas')['cuerpo']);
        self::assertStringContainsString(
            '<a href="?page=tasas-csv&amp;moneda=CLP">Exportar CSV</a>',
            $this->get('page=tasas&moneda=CLP&pagina=1')['cuerpo'],
            'el enlace del historial lleva la moneda y no la pagina'
        );
    }
}
