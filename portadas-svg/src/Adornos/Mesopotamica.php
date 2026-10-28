<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición mesopotámica: la pirámide escalonada (zigurat) en el friso, y la roseta asiria en el emblema. */
final class Mesopotamica extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $h = $zona->alto;
        $abajo = $zona->y + $h;

        return self::repetir($zona, $h * 2.4, static function (float $x, float $w) use ($h, $zona, $abajo, $color, $grosor): string {
            $arriba = $zona->y;

            return self::linea([
                [$x, $abajo], [$x, $abajo - $h / 3], [$x + $w / 6, $abajo - $h / 3], [$x + $w / 6, $abajo - 2 * $h / 3],
                [$x + $w / 3, $abajo - 2 * $h / 3], [$x + $w / 3, $arriba], [$x + 2 * $w / 3, $arriba],
                [$x + 2 * $w / 3, $abajo - 2 * $h / 3], [$x + 5 * $w / 6, $abajo - 2 * $h / 3], [$x + 5 * $w / 6, $abajo - $h / 3],
                [$x + $w, $abajo - $h / 3], [$x + $w, $abajo],
            ], $color, $grosor) . self::linea([[$x, $abajo], [$x + $w, $abajo]], $color, $grosor);
        });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color): string
    {
        $grosor = self::grosor($radio);
        $r = $radio - $grosor;
        $svg = self::circulo($cx, $cy, $r, $color, $grosor);
        // Ocho pétalos redondos alrededor de un botón central.
        for ($i = 0; $i < 8; $i++) {
            $angulo = 45 * $i;
            $svg .= self::linea(self::elipse($cx + 0.6 * $r * cos(deg2rad($angulo)), $cy + 0.6 * $r * sin(deg2rad($angulo)), $r * 0.28, $r * 0.13, $angulo), $color, $grosor, true);
        }

        return $svg . self::circulo($cx, $cy, $r * 0.2, $color, $grosor);
    }
}
