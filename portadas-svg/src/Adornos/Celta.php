<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición celta: parejas de espirales en el friso, y el triskel (tres espirales) en el emblema. */
final class Celta extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $cy = $zona->y + $zona->alto / 2;

        return self::repetir($zona, $zona->alto * 4, static function (float $x, float $ancho) use ($zona, $cy, $color, $grosor): string {
            $r = min($zona->alto / 2 - $grosor, $ancho / 4);
            $izquierda = $x + $ancho / 4;
            $derecha = $x + 3 * $ancho / 4;

            // Dos espirales que se dan la espalda unidas por un trazo recto: una S de lado.
            return self::linea(self::espiral($izquierda, $cy, $r, 0, 1.4), $color, $grosor)
                . self::linea(self::espiral($derecha, $cy, $r, 180, 1.4), $color, $grosor)
                . self::linea([[$izquierda + $r, $cy], [$derecha - $r, $cy]], $color, $grosor);
        });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color, ?float $trazo = null): string
    {
        $grosor = $trazo ?? self::grosor($radio);
        $r = $radio - $grosor;
        $svg = '';
        foreach ([270, 30, 150] as $angulo) {
            $rad = deg2rad($angulo);
            $centroX = $cx + 0.55 * $r * cos($rad);
            $centroY = $cy + 0.55 * $r * sin($rad);
            $svg .= self::linea(self::espiral($centroX, $centroY, 0.42 * $r, $angulo + 180, 1.15), $color, $grosor)
                . self::linea([[$cx, $cy], [$centroX + 0.42 * $r * cos($rad + M_PI), $centroY + 0.42 * $r * sin($rad + M_PI)]], $color, $grosor);
        }

        return $svg;
    }
}
