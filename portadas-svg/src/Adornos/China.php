<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición china: nubes en espiral (yun wen) en el friso, y un disco bi con su cuadrado en el emblema. */
final class China extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $abajo = $zona->y + $zona->alto;
        $r = $zona->alto;

        // Cada nube son tres arcos concéntricos sobre la línea de base: una cabeza de nube con su cola.
        return self::repetir($zona, $r * 3.2, function (float $x, float $ancho) use ($r, $abajo, $color, $grosor): string {
            $cx = $x + $ancho * 0.38;
            $r = min($r, $ancho * 0.38); // en una zona muy estrecha, la nube se achica en vez de salirse

            return self::linea(self::arco($cx, $abajo, $r, 180, 360), $color, $grosor)
                . self::linea(self::arco($cx, $abajo, $r * 0.62, 180, 360), $color, $grosor)
                . self::linea(self::arco($cx, $abajo, $r * 0.28, 180, 360), $color, $grosor)
                . self::linea([[$x, $abajo], [$cx - $r, $abajo]], $color, $grosor)
                . self::linea([[$cx + $r, $abajo], [$x + $ancho, $abajo]], $color, $grosor);
        });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color): string
    {
        $grosor = self::grosor($radio);
        $r = $radio - $grosor; // el trazo tiene ancho: se resta para que todo quede dentro de `$radio`

        // Disco bi: dos círculos con un cuadrado girado entre ellos.
        return self::circulo($cx, $cy, $r, $color, $grosor)
            . self::linea(self::poligono($cx, $cy, $r * 0.8, 4, 45), $color, $grosor, true)
            . self::circulo($cx, $cy, $r * 0.42, $color, $grosor);
    }
}
