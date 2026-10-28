<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición japonesa: círculos enlazados (shippo) en el friso, y un crisantemo sencillo en el emblema. */
final class Japonesa extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $r = min($zona->alto / 2, $zona->ancho / 2);
        $cy = $zona->y + $zona->alto / 2;
        // Tantos círculos como caben con un solape de la cuarta parte, repartidos de borde a borde.
        $cuantos = max(1, (int) floor(($zona->ancho - 2 * $r) / ($r * 1.5)) + 1);
        $paso = $cuantos > 1 ? ($zona->ancho - 2 * $r) / ($cuantos - 1) : 0.0;

        $svg = '';
        for ($i = 0; $i < $cuantos; $i++) {
            $svg .= self::circulo($cuantos > 1 ? $zona->x + $r + $i * $paso : $zona->x + $zona->ancho / 2, $cy, $r - $grosor / 2, $color, $grosor);
        }

        return $svg;
    }

    public function emblema(float $cx, float $cy, float $radio, string $color): string
    {
        $grosor = self::grosor($radio);
        $r = $radio - $grosor;
        $svg = self::circulo($cx, $cy, $r, $color, $grosor) . self::circulo($cx, $cy, $r * 0.28, $color, $grosor);
        for ($i = 0; $i < 16; $i++) {
            $angulo = deg2rad(360 * $i / 16);
            $svg .= self::linea([[$cx + $r * 0.42 * cos($angulo), $cy + $r * 0.42 * sin($angulo)], [$cx + $r * 0.88 * cos($angulo), $cy + $r * 0.88 * sin($angulo)]], $color, $grosor);
        }

        return $svg;
    }
}
