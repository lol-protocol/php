<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición europea moderna: un filete con rombos en el friso, y una estrella de cuatro puntas en el emblema. */
final class Moderna extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $cy = $zona->y + $zona->alto / 2;
        $r = $zona->alto * 0.28;

        return self::linea([[$zona->x, $cy], [$zona->x + $zona->ancho, $cy]], $color, $grosor)
            . self::repetir($zona, $zona->alto * 3, static function (float $x, float $ancho) use ($cy, $r, $color, $grosor): string {
                return self::linea(self::poligono($x + $ancho / 2, $cy, $r, 4, 0), $color, $grosor, true);
            });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color): string
    {
        $grosor = self::grosor($radio);
        $r = $radio - $grosor;
        $puntas = [];
        for ($i = 0; $i < 8; $i++) {
            $angulo = deg2rad(45 * $i - 90);
            $largo = $i % 2 === 0 ? $r : $r * 0.22;
            $puntas[] = [$cx + $largo * cos($angulo), $cy + $largo * sin($angulo)];
        }

        return self::linea($puntas, $color, $grosor, true) . self::circulo($cx, $cy, $r * 0.55, $color, $grosor);
    }
}
