<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;
use PortadasSvg\Svg;

/** Sin tradición, o con una que aún no tiene adorno propio: un rombo y ningún friso. */
final class Generico extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        return '';
    }

    public function emblema(float $cx, float $cy, float $radio, string $color, ?float $trazo = null): string
    {
        $r = $radio * $this->ocupa();
        $punto = static fn (float $x, float $y): string => Svg::n($x) . ',' . Svg::n($y);

        return Svg::elemento('polygon', [
            'points' => implode(' ', [$punto($cx, $cy - $r), $punto($cx + $r, $cy), $punto($cx, $cy + $r), $punto($cx - $r, $cy)]),
            'fill' => $color,
        ]);
    }

    public function ocupa(): float
    {
        return 0.5;
    }
}
