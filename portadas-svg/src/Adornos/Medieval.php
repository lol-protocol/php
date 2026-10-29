<?php

declare(strict_types=1);

namespace PortadasSvg\Adornos;

use PortadasSvg\Rectangulo;

/** Tradición medieval europea: el entrelazado de las iniciales y las orlas, y el cuadrilóbulo gótico. */
final class Medieval extends Adorno
{
    public function friso(Rectangulo $zona, string $color): string
    {
        $grosor = self::grosor($zona->alto);
        $cy = $zona->y + $zona->alto / 2;
        $amplitud = $zona->alto / 2 - $grosor;

        // Dos ondas en oposición de fase que se cruzan, como una trenza.
        return self::repetir($zona, $zona->alto * 2, function (float $x, float $ancho) use ($cy, $amplitud, $color, $grosor): string {
            $onda = static function (float $signo) use ($x, $ancho, $cy, $amplitud): array {
                $puntos = [];
                for ($i = 0; $i <= self::SEGMENTOS; $i++) {
                    $t = $i / self::SEGMENTOS;
                    $puntos[] = [$x + $ancho * $t, $cy + $signo * $amplitud * sin(2 * M_PI * $t)];
                }

                return $puntos;
            };

            return self::linea($onda(1.0), $color, $grosor) . self::linea($onda(-1.0), $color, $grosor);
        });
    }

    public function emblema(float $cx, float $cy, float $radio, string $color, ?float $trazo = null): string
    {
        $grosor = $trazo ?? self::grosor($radio);
        $lobulo = ($radio - $grosor / 2) / 2; // cada lóbulo mide la mitad del radio, con su trazo dentro
        $svg = '';
        foreach ([0, 90, 180, 270] as $angulo) {
            $svg .= self::circulo($cx + $lobulo * cos(deg2rad($angulo)), $cy + $lobulo * sin(deg2rad($angulo)), $lobulo, $color, $grosor);
        }

        return $svg . self::circulo($cx, $cy, $lobulo * 0.25, $color, $grosor, true);
    }
}
