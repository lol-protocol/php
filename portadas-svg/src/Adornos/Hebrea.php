<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición hebrea: granadas con su corona en el friso, y doce piedras en círculo en el emblema. */
final class Hebrea extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $h = $zona->alto;
        $y = $zona->y;
        $fin = $zona->x + $zona->ancho;

        return self::repetir($zona, $h * 2, function (float $x, float $ancho) use ($h, $y, $fin, $color, $grosor): string {
            $cx = $x + $ancho / 2;
            $svg = self::circulo($cx, $y + $h * 0.64, $h * 0.28, $color, $grosor)
                . self::linea([
                    [$cx - 0.14 * $h, $y + 0.37 * $h], [$cx - 0.14 * $h, $y + 0.12 * $h], [$cx - 0.07 * $h, $y + 0.26 * $h], [$cx, $y + 0.08 * $h],
                    [$cx + 0.07 * $h, $y + 0.26 * $h], [$cx + 0.14 * $h, $y + 0.12 * $h], [$cx + 0.14 * $h, $y + 0.37 * $h],
                ], $color, $grosor);
            // Una hoja entre cada granada y la siguiente.
            if ($x + $ancho < $fin - 1e-6) {
                $svg .= self::linea(self::hoja($x + $ancho, $y + $h * 0.64, $h * 0.56, $h * 0.2), $color, $grosor, true);
            }

            return $svg;
        });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color, ?float $trazo = null): string
    {
        $grosor = $trazo ?? self::grosor($radio);
        $r = $radio - $grosor;
        $svg = '';
        for ($i = 0; $i < 12; $i++) {
            $angulo = 30 * $i;
            $svg .= self::circulo($cx + 0.8 * $r * cos(deg2rad($angulo)), $cy + 0.8 * $r * sin(deg2rad($angulo)), $r * 0.17, $color, $grosor);
        }

        return $svg . self::circulo($cx, $cy, $r * 0.5, $color, $grosor) . self::circulo($cx, $cy, $r * 0.16, $color, $grosor, true);
    }
}
