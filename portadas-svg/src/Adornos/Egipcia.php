<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición egipcia: flores de loto en el friso, y el disco solar con alas en el emblema. */
final class Egipcia extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $h = $zona->alto;
        $abajo = $zona->y + $h;
        $fin = $zona->x + $zona->ancho;
        $base = self::linea([[$zona->x, $abajo], [$fin, $abajo]], $color, $grosor);

        return $base . self::repetir($zona, $h * 2.2, function (float $x, float $ancho) use ($h, $abajo, $fin, $color, $grosor): string {
            $cx = $x + $ancho / 2;
            // Tres pétalos de loto: el del centro derecho y los dos de los lados abiertos.
            $svg = self::linea(self::hoja($cx, $abajo - $h * 0.5, $h * 0.9, $h * 0.32, 90), $color, $grosor, true)
                . self::linea(self::hoja($cx - $h * 0.3, $abajo - $h * 0.42, $h * 0.76, $h * 0.28, 90 - 25), $color, $grosor, true)
                . self::linea(self::hoja($cx + $h * 0.3, $abajo - $h * 0.42, $h * 0.76, $h * 0.28, 90 + 25), $color, $grosor, true);
            if ($x + $ancho < $fin - 1e-6) {
                $svg .= self::circulo($x + $ancho, $abajo - $h * 0.5, $h * 0.07, $color, $grosor, true);
            }

            return $svg;
        });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color, ?float $trazo = null): string
    {
        $grosor = $trazo ?? self::grosor($radio);
        $r = $radio - $grosor;
        $svg = self::circulo($cx, $cy, $r * 0.27, $color, $grosor);
        foreach ([-1, 1] as $lado) {
            // Tres plumas por ala, que suben hacia la punta.
            foreach ([0, 1, 2] as $pluma) {
                $puntos = [];
                for ($i = 0; $i <= 12; $i++) {
                    $t = $i / 12;
                    $puntos[] = [$cx + $lado * (0.36 + 0.64 * $t) * $r, $cy + ($pluma - 1) * 0.2 * $r * (1 + 0.6 * $t) - 0.45 * $r * $t * $t];
                }
                $svg .= self::linea($puntos, $color, $grosor);
            }
        }

        return $svg;
    }
}
