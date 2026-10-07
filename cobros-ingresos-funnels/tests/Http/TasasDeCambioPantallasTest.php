<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Database;
use PDO;

/**
 * Las pantallas que muestran totales en USD (Dashboard, Cobros, Pagos y Cohortes)
 * avisan que tan reales son las tasas con las que los convierten: de ejemplo,
 * parcialmente reales, o reales y de que fecha. Las que no muestran dinero consolidado
 * no dicen nada.
 *
 * El servidor lee monedas con su propia conexion, asi que el estado de cada caso va
 * commiteado, y al terminar cada test se restauran las columnas de origen tal como
 * estaban.
 */
final class TasasDeCambioPantallasTest extends HttpTestCase
{
    private const PANTALLAS_EN_USD = ['dashboard', 'cobros', 'pagos', 'cohortes'];

    protected function setUp(): void
    {
        parent::setUp();
        $db = Database::connection();
        /** @var array<string, array{tasa_actualizada_en: ?string, tasa_fuente: ?string}> $antes */
        $antes = $db->query('SELECT codigo, tasa_actualizada_en, tasa_fuente FROM monedas')->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
        $this->alTerminar(static function () use ($antes): void {
            $restaurar = Database::connection()->prepare('UPDATE monedas SET tasa_actualizada_en = :cuando, tasa_fuente = :fuente WHERE codigo = :codigo');
            foreach ($antes as $codigo => $fila) {
                $restaurar->execute([':cuando' => $fila['tasa_actualizada_en'], ':fuente' => $fila['tasa_fuente'], ':codigo' => $codigo]);
            }
        });
    }

    private function nuncaActualizadas(): void
    {
        Database::connection()->exec('UPDATE monedas SET tasa_actualizada_en = NULL, tasa_fuente = NULL');
    }

    private function actualizadasHaceUnaHora(): void
    {
        Database::connection()->exec("UPDATE monedas SET tasa_actualizada_en = now() - interval '1 hour', tasa_fuente = 'fuente-de-prueba' WHERE codigo <> 'USD'");
    }

    private function monedaConMovimientos(): string
    {
        $codigo = Database::connection()->query("SELECT moneda_codigo FROM boletas WHERE moneda_codigo <> 'USD' GROUP BY moneda_codigo ORDER BY count(*) DESC LIMIT 1")->fetchColumn();
        self::assertIsString($codigo);

        return $codigo;
    }

    public function testSiLasTasasSonDeEjemploCadaPantallaEnUsdLoAvisa(): void
    {
        $this->nuncaActualizadas();

        foreach (self::PANTALLAS_EN_USD as $pantalla) {
            $cuerpo = $this->get("page={$pantalla}")['cuerpo'];

            self::assertStringContainsString('Las tasas de cambio son de ejemplo, no reales', $cuerpo, $pantalla);
            self::assertStringContainsString('php database/actualizar_tasas.php', $cuerpo, "{$pantalla} dice como cargar las reales");
            self::assertStringNotContainsString('Cifras en USD a la tasa de cambio del', $cuerpo, $pantalla);
        }
    }

    public function testConTodasAlDiaDiceDeQueFechaSonYDeDondeVienen(): void
    {
        $this->actualizadasHaceUnaHora();

        foreach (self::PANTALLAS_EN_USD as $pantalla) {
            $cuerpo = $this->get("page={$pantalla}")['cuerpo'];

            self::assertStringContainsString('Cifras en USD a la tasa de cambio del', $cuerpo, $pantalla);
            self::assertStringContainsString('(fuente-de-prueba)', $cuerpo, $pantalla);
            self::assertStringContainsString('también las boletas y los pagos de meses anteriores', $cuerpo, "{$pantalla} avisa que se convierte con la tasa de hoy");
            self::assertStringNotContainsString('son de ejemplo', $cuerpo, $pantalla);
            self::assertStringNotContainsString('sin actualizarse', $cuerpo, $pantalla);
        }
    }

    public function testSiUnaMonedaConMovimientosSigueDeEjemploLaNombra(): void
    {
        $moneda = $this->monedaConMovimientos();
        $this->actualizadasHaceUnaHora();
        Database::connection()->prepare('UPDATE monedas SET tasa_actualizada_en = NULL WHERE codigo = :codigo')->execute([':codigo' => $moneda]);

        foreach (self::PANTALLAS_EN_USD as $pantalla) {
            $cuerpo = $this->get("page={$pantalla}")['cuerpo'];

            self::assertMatchesRegularExpression('/de ejemplo o lleva más de 7 días sin actualizarse: ' . preg_quote($moneda, '/') . '\./', $cuerpo, $pantalla);
            self::assertStringNotContainsString('Cifras en USD a la tasa de cambio del', $cuerpo, "{$pantalla}: con una moneda pendiente no se afirma que todo este al dia");
        }
    }

    public function testLasPantallasSinDineroConsolidadoNoDicenNada(): void
    {
        $this->nuncaActualizadas();

        foreach (['funnel', 'clientes', 'auditoria'] as $pantalla) {
            $cuerpo = $this->get("page={$pantalla}")['cuerpo'];

            self::assertStringNotContainsString('tasas de cambio', $cuerpo, $pantalla);
            self::assertStringNotContainsString('Cifras en USD', $cuerpo, $pantalla);
        }
    }
}
