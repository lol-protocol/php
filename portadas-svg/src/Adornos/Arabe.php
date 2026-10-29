<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición árabe: la estrella de ocho puntas (dos cuadrados cruzados) de la geometría islámica. */
final class Arabe extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $cy = $zona->y + $zona->alto / 2;
        $r = $zona->alto / 2;

        // Estrellas unidas por rombos pequeños, entre dos filetes.
        $svg = self::linea([[$zona->x, $zona->y], [$zona->x + $zona->ancho, $zona->y]], $color, $grosor)
            . self::linea([[$zona->x, $zona->y + $zona->alto], [$zona->x + $zona->ancho, $zona->y + $zona->alto]], $color, $grosor);

        $fin = $zona->x + $zona->ancho;

        return $svg . self::repetir($zona, $r * 3, function (float $x, float $ancho) use ($r, $cy, $fin, $color, $grosor): string {
            $cx = $x + $ancho / 2;
            $estrella = self::linea(self::poligono($cx, $cy, $r * 0.85, 4, 0), $color, $grosor, true)
                . self::linea(self::poligono($cx, $cy, $r * 0.85, 4, 45), $color, $grosor, true);
            // El rombo que une cada estrella con la siguiente no se pone en el borde de la zona.
            $union = $x + $ancho < $fin - 1e-6 ? self::linea(self::poligono($x + $ancho, $cy, $r * 0.3, 4, 0), $color, $grosor, true) : '';

            return $estrella . $union;
        });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color, ?float $trazo = null): string
    {
        $grosor = $trazo ?? self::grosor($radio);
        $r = $radio - $grosor; // el trazo tiene ancho, y las puntas de la estrella más

        return self::linea(self::poligono($cx, $cy, $r, 4, 0), $color, $grosor, true)
            . self::linea(self::poligono($cx, $cy, $r, 4, 45), $color, $grosor, true)
            . self::circulo($cx, $cy, $r * 0.5, $color, $grosor)
            . self::linea(self::poligono($cx, $cy, $r * 0.3, 8, 22.5), $color, $grosor, true);
    }
}
