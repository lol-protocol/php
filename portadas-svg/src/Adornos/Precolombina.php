<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición precolombina: una escalinata en el friso, y la chakana (la cruz escalonada andina) en el emblema. */
final class Precolombina extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $h = $zona->alto;
        $abajo = $zona->y + $h;

        return self::repetir($zona, $h * 1.8, static function (float $x, float $w) use ($h, $abajo, $zona, $color, $grosor): string {
            return self::linea([
                [$x, $abajo], [$x, $abajo - $h / 4], [$x + $w / 4, $abajo - $h / 4], [$x + $w / 4, $abajo - $h / 2],
                [$x + $w / 2, $abajo - $h / 2], [$x + $w / 2, $abajo - 3 * $h / 4], [$x + 3 * $w / 4, $abajo - 3 * $h / 4],
                [$x + 3 * $w / 4, $zona->y], [$x + $w, $zona->y], [$x + $w, $abajo],
            ], $color, $grosor);
        }) . self::linea([[$zona->x, $abajo], [$zona->x + $zona->ancho, $abajo]], $color, $grosor);
    }

    public function emblema(float $cx, float $cy, float $radio, string $color): string
    {
        $grosor = self::grosor($radio);
        $r = $radio - $grosor;
        $unidad = [
            [-0.2, -1.0], [0.2, -1.0], [0.2, -0.6], [0.6, -0.6], [0.6, -0.2], [1.0, -0.2], [1.0, 0.2], [0.6, 0.2], [0.6, 0.6], [0.2, 0.6],
            [0.2, 1.0], [-0.2, 1.0], [-0.2, 0.6], [-0.6, 0.6], [-0.6, 0.2], [-1.0, 0.2], [-1.0, -0.2], [-0.6, -0.2], [-0.6, -0.6], [-0.2, -0.6],
        ];

        return self::linea(array_map(static fn (array $punto): array => [$cx + $punto[0] * $r, $cy + $punto[1] * $r], $unidad), $color, $grosor, true)
            . self::circulo($cx, $cy, $r * 0.16, $color, $grosor);
    }
}
