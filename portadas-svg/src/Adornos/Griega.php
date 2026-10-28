<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición griega: el meandro, la greca de espirales cuadradas que rodeaba los vasos y los templos. */
final class Griega extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $celda = $zona->alto / 6;
        $y = $zona->y;
        $abajo = $y + $zona->alto;

        // Cada módulo es una espiral cuadrada de 6 celdas de lado; entre una y otra corre la línea de base.
        return self::repetir($zona, $celda * 7, static function (float $x, float $ancho) use ($celda, $y, $abajo, $color, $grosor): string {
            $c = $ancho / 7; // el módulo se estira a lo ancho, no a lo alto
            $h = $celda;

            return self::linea([
                [$x, $abajo], [$x, $y], [$x + 6 * $c, $y], [$x + 6 * $c, $abajo],
                [$x + 2 * $c, $abajo], [$x + 2 * $c, $y + 2 * $h], [$x + 4 * $c, $y + 2 * $h], [$x + 4 * $c, $y + 4 * $h],
            ], $color, $grosor) . self::linea([[$x + 6 * $c, $abajo], [$x + 7 * $c, $abajo]], $color, $grosor);
        });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color, ?float $trazo = null): string
    {
        $grosor = $trazo ?? self::grosor($radio);
        $lado = $radio * 2 * 0.9;
        $x = $cx - $lado / 2;
        $y = $cy - $lado / 2;
        $c = $lado / 6;

        // La misma espiral cuadrada del friso, sola, dentro de un marco.
        return self::linea([[$x, $y], [$x + $lado, $y], [$x + $lado, $y + $lado], [$x, $y + $lado]], $color, $grosor, true)
            . self::linea([
                [$x + $c, $y + 5 * $c], [$x + $c, $y + $c], [$x + 5 * $c, $y + $c], [$x + 5 * $c, $y + 5 * $c],
                [$x + 2.5 * $c, $y + 5 * $c], [$x + 2.5 * $c, $y + 2.5 * $c], [$x + 3.5 * $c, $y + 2.5 * $c], [$x + 3.5 * $c, $y + 3.5 * $c],
            ], $color, $grosor);
    }
}
