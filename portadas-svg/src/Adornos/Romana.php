<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición romana: una arcada de medio punto en el friso, y la corona de laurel en el emblema. */
final class Romana extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $abajo = $zona->y + $zona->alto;
        $base = self::linea([[$zona->x, $abajo], [$zona->x + $zona->ancho, $abajo]], $color, $grosor);

        return $base . self::repetir($zona, $zona->alto * 1.4, function (float $x, float $ancho) use ($zona, $abajo, $color, $grosor): string {
            $cx = $x + $ancho / 2;
            $r = min($ancho * 0.35, $zona->alto * 0.8);
            $arranque = $zona->y + $r;

            return self::linea(self::arco($cx, $arranque, $r, 180, 360), $color, $grosor)
                . self::linea([[$cx - $r, $arranque], [$cx - $r, $abajo]], $color, $grosor)
                . self::linea([[$cx + $r, $arranque], [$cx + $r, $abajo]], $color, $grosor);
        });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color): string
    {
        $grosor = self::grosor($radio);
        $r = $radio - $grosor;
        $svg = '';
        // Doce hojas de laurel, todas inclinadas hacia fuera, alrededor de un círculo.
        for ($i = 0; $i < 12; $i++) {
            $angulo = 30 * $i;
            $svg .= self::linea(self::hoja($cx + 0.74 * $r * cos(deg2rad($angulo)), $cy + 0.74 * $r * sin(deg2rad($angulo)), $r * 0.46, $r * 0.2, $angulo + 90 + 30), $color, $grosor, true);
        }

        return $svg . self::circulo($cx, $cy, $r * 0.34, $color, $grosor);
    }
}
