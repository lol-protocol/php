<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Las columnas de las grillas (public/assets/style.css) no pueden pedir un ancho minimo fijo. Con
 * `minmax(360px, 1fr)`, un telefono de 360 px (el contenedor deja 312 px utiles) no tenia donde poner la
 * columna y empujaba toda la pagina 24 px hacia un lado; a 320 px, 64 px. La forma correcta es
 * `minmax(min(360px, 100%), 1fr)`: la columna se achica a lo que hay.
 *
 * Es una guarda sobre el texto del CSS, porque la CI no abre un navegador: lo que midio el desborde fue un
 * Chromium a 420, 390, 360 y 320 px sobre las 15 pantallas, y esta prueba solo evita que vuelva la causa.
 */
final class GrillasDelEstiloTest extends TestCase
{
    private static function css(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/style.css');
    }

    public function testNingunaColumnaDeGridTieneUnAnchoMinimoFijo(): void
    {
        preg_match_all('/minmax\(\s*(\d+(?:\.\d+)?(?:px|rem|em))/', self::css(), $coincidencias);

        self::assertSame([], $coincidencias[0], 'usar minmax(min(Npx, 100%), 1fr) para que la columna quepa en una pantalla chica');
    }

    public function testLasGrillasQueSeAdaptanUsanMinConElCienPorCiento(): void
    {
        preg_match_all('/grid-template-columns:\s*repeat\(auto-fit,\s*minmax\(min\((\d+)px,\s*100%\),\s*1fr\)\)/', self::css(), $coincidencias);

        self::assertSame(['190', '360', '230', '140'], $coincidencias[1], 'las cuatro grillas: kpis, dos paneles, segmentos y el perfil del cliente');
    }
}
