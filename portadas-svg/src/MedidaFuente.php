<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Medida con la tabla de anchos de una fuente (ver datos/anchos.json). No tiene en cuenta el kerning,
 * que casi siempre acorta una línea, así que la medida sale igual o algo mayor que la real, no menor.
 */
final class MedidaFuente implements Medida
{
    /** @param array<int, int> $anchos código Unicode => ancho en milésimas de em */
    public function __construct(
        private readonly array $anchos,
        /** Ancho que se da a una letra que la fuente no tiene: la media de las minúsculas. */
        private readonly int $promedio,
        private readonly int $unidades,
    ) {
    }

    public function em(string $texto): float
    {
        $suma = 0;
        foreach (mb_str_split($texto) as $letra) {
            $codigo = mb_ord($letra);
            if ($codigo < 0x20) {
                continue;
            }
            $suma += $this->anchos[$codigo] ?? $this->promedio;
        }

        return $suma / $this->unidades;
    }

    /** Las letras de `texto` que la fuente no tiene, sin repetir y en el orden en que salen. */
    public function noCubiertas(string $texto): string
    {
        $faltan = [];
        foreach (mb_str_split($texto) as $letra) {
            $codigo = mb_ord($letra);
            if ($codigo >= 0x20 && !isset($this->anchos[$codigo])) {
                $faltan[$letra] = true;
            }
        }

        return implode('', array_keys($faltan));
    }
}
