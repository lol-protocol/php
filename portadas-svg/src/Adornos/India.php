<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición india: una fila de pétalos de loto en el friso, y el mandala de pétalos en el emblema. */
final class India extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $h = $zona->alto;
        $abajo = $zona->y + $h;

        return self::repetir($zona, $h * 1.1, static function (float $x, float $ancho) use ($h, $abajo, $color, $grosor): string {
            $w = min($ancho, $h * 1.15);
            $x0 = $x + ($ancho - $w) / 2;
            $pintar = static function (float $escala) use ($x0, $w, $abajo, $color, $grosor): string {
                // Arco apuntado: dos arcos de radio `w` que se cortan arriba, en el centro.
                $ancho = $w * $escala;
                $izq = $x0 + ($w - $ancho) / 2;

                return self::linea(self::arco($izq + $ancho, $abajo, $ancho, 180, 240), $color, $grosor)
                    . self::linea(self::arco($izq, $abajo, $ancho, 300, 360), $color, $grosor);
            };

            return $pintar(1.0) . $pintar(0.55);
        }) . self::linea([[$zona->x, $abajo], [$zona->x + $zona->ancho, $abajo]], $color, $grosor);
    }

    public function emblema(float $cx, float $cy, float $radio, string $color, ?float $trazo = null): string
    {
        $grosor = $trazo ?? self::grosor($radio);
        $r = $radio - $grosor;
        $svg = '';
        // Ocho pétalos de loto con su centro.
        for ($i = 0; $i < 8; $i++) {
            $angulo = 45 * $i;
            $svg .= self::linea(self::hoja($cx + 0.55 * $r * cos(deg2rad($angulo)), $cy + 0.55 * $r * sin(deg2rad($angulo)), $r * 0.9, $r * 0.32, $angulo), $color, $grosor, true);
        }

        return $svg . self::circulo($cx, $cy, $r * 0.2, $color, $grosor);
    }
}
