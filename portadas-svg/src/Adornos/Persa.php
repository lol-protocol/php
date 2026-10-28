<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición persa: el boteh (la gota curvada) en el friso, y el medallón de rayos (shamsa) en el emblema. */
final class Persa extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $h = $zona->alto;
        $fin = $zona->x + $zona->ancho;
        $cy = $zona->y + $h / 2;

        return self::repetir($zona, $h * 2, function (float $x, float $ancho) use ($h, $cy, $fin, $color, $grosor): string {
            $cx = $x + $ancho / 2;
            $gota = [];
            for ($i = 0; $i < self::SEGMENTOS; $i++) {
                $t = 2 * M_PI * $i / self::SEGMENTOS;
                $v = -cos($t); // -1 en la punta, arriba; 1 abajo
                $gota[] = [$cx + 0.4 * $h * sin($t) * sin($t / 2) ** 1.4 - 0.28 * $h * $v, $cy + 0.47 * $h * $v];
            }
            $svg = self::linea($gota, $color, $grosor, true);
            if ($x + $ancho < $fin - 1e-6) {
                $svg .= self::circulo($x + $ancho, $cy, $h * 0.08, $color, $grosor, true);
            }

            return $svg;
        });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color): string
    {
        $grosor = self::grosor($radio);
        $r = $radio - $grosor;
        $rayos = [];
        for ($i = 0; $i < 32; $i++) {
            $angulo = deg2rad(360 * $i / 32);
            $largo = $i % 2 === 0 ? $r : $r * 0.78;
            $rayos[] = [$cx + $largo * cos($angulo), $cy + $largo * sin($angulo)];
        }

        return self::linea($rayos, $color, $grosor, true)
            . self::circulo($cx, $cy, $r * 0.55, $color, $grosor)
            . self::circulo($cx, $cy, $r * 0.22, $color, $grosor);
    }
}
