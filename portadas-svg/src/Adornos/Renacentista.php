<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición renacentista: una onda con perlas (rinceau) en el friso, y una cartela ovalada en el emblema. */
final class Renacentista extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $h = $zona->alto;
        $cy = $zona->y + $h / 2;
        $amplitud = $h * 0.22;

        return self::repetir($zona, $h * 2.4, static function (float $x, float $ancho) use ($h, $cy, $amplitud, $color, $grosor): string {
            $onda = [];
            for ($i = 0; $i <= self::SEGMENTOS; $i++) {
                $t = $i / self::SEGMENTOS;
                $onda[] = [$x + $ancho * $t, $cy - $amplitud * sin(2 * M_PI * $t)];
            }

            // Una perla sobre cada cresta y otra bajo cada seno, fuera de la onda.
            return self::linea($onda, $color, $grosor)
                . self::circulo($x + $ancho / 4, $cy - $amplitud - $h * 0.13, $h * 0.13 - $grosor / 2, $color, $grosor)
                . self::circulo($x + 3 * $ancho / 4, $cy + $amplitud + $h * 0.13, $h * 0.13 - $grosor / 2, $color, $grosor);
        });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color): string
    {
        $grosor = self::grosor($radio);
        $r = $radio - $grosor;

        return self::linea(self::elipse($cx, $cy, $r, $r * 0.72), $color, $grosor, true)
            . self::linea(self::elipse($cx, $cy, $r * 0.82, $r * 0.58), $color, $grosor, true)
            . self::linea(self::poligono($cx, $cy, $r * 0.2, 4, 0), $color, $grosor, true);
    }
}
