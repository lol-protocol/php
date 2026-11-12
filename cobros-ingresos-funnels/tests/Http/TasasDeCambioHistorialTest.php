<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;

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
}
