<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición nórdica: dientes de lobo (triángulos anidados) en el friso, y la flor de seis pétalos en el emblema. */
final class Nordica extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $h = $zona->alto;
        $abajo = $zona->y + $h;

        return self::repetir($zona, $h * 1.2, static function (float $x, float $ancho) use ($h, $abajo, $zona, $color, $grosor): string {
            $cx = $x + $ancho / 2;

            return self::linea([[$x, $abajo], [$cx, $zona->y], [$x + $ancho, $abajo]], $color, $grosor)
                . self::linea([[$x + $ancho * 0.25, $abajo], [$cx, $zona->y + $h * 0.5], [$x + $ancho * 0.75, $abajo]], $color, $grosor);
        }) . self::linea([[$zona->x, $abajo], [$zona->x + $zona->ancho, $abajo]], $color, $grosor);
    }

    public function emblema(float $cx, float $cy, float $radio, string $color): string
    {
        $grosor = self::grosor($radio);
        $r = $radio - $grosor / 2;
        $svg = self::circulo($cx, $cy, $r, $color, $grosor);
        // Seis círculos de radio mitad, cada uno por el centro del anterior: la flor trazada con compás.
        for ($i = 0; $i < 6; $i++) {
            $angulo = deg2rad(60 * $i);
            $svg .= self::circulo($cx + $r / 2 * cos($angulo), $cy + $r / 2 * sin($angulo), $r / 2, $color, $grosor);
        }

        return $svg;
    }
}
