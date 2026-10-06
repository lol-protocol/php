<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\View;
use PHPUnit\Framework\TestCase;

/**
 * El pie del layout decia "Datos de ejemplo generados localmente" siempre,
 * tambien en una instalacion de produccion con datos reales.
 */
final class LayoutTest extends TestCase
{
    private string|false $appEnvOriginal;

    protected function setUp(): void
    {
        $this->appEnvOriginal = getenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        putenv($this->appEnvOriginal === false ? 'APP_ENV' : "APP_ENV={$this->appEnvOriginal}");
    }

    private function pie(): string
    {
        ob_start();
        View::render('_error', ['error' => null, 'titulo' => 'Prueba', 'activePage' => 'dashboard']);
        $html = (string) ob_get_clean();

        if (preg_match('~<footer>(.*?)</footer>~s', $html, $coincidencia) !== 1) {
            self::fail('El layout no tiene <footer>.');
        }

        return $coincidencia[1];
    }

    public function testEnDesarrolloElPieAvisaQueSonDatosDeEjemplo(): void
    {
        putenv('APP_ENV=dev');

        self::assertStringContainsString('Datos de ejemplo generados localmente', $this->pie());
    }

    public function testFueraDeDesarrolloElPieNoDiceQueSonDatosDeEjemplo(): void
    {
        foreach (['produccion', ''] as $entorno) {
            putenv("APP_ENV={$entorno}");
            self::assertStringNotContainsString('Datos de ejemplo', $this->pie(), "APP_ENV='{$entorno}'");
        }

        putenv('APP_ENV');
        $pie = $this->pie();
        self::assertStringNotContainsString('Datos de ejemplo', $pie, 'sin APP_ENV es produccion');
        self::assertStringContainsString('Panel de Cobros', $pie, 'el nombre del sistema sigue estando');
    }
}
