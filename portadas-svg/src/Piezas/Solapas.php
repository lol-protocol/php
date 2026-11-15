<?php

declare(strict_types=1);

namespace PortadasSvg\Piezas;

use PortadasSvg\Libro;
use PortadasSvg\Rectangulo;
use PortadasSvg\Texto;

/**
 * Las solapas interiores de una plantilla: texto arriba a la izquierda, con su margen.
 *
 * Es parte de `Plantilla` (se usa desde ella) y se apoya en sus ayudas de texto y de dibujo.
 */
trait Solapas
{
    /** Solapas: margen interior y tamaño del texto. */
    protected const SOLAPA_MARGEN = 10.0;
    protected const SOLAPA_TAMANO_MAX = 4.0;
    protected const SOLAPA_TAMANO_MIN = 2.8;

    /** Solapa interior: texto arriba a la izquierda, con su margen. */
    protected function solapa(string $texto, Libro $libro, Rectangulo $panel): string
    {
        if (trim($texto) === '') {
            return '';
        }

        $margen = self::SOLAPA_MARGEN;
        $ajuste = Texto::ajustar($texto, $panel->ancho - 2 * $margen, $panel->alto - 2 * $margen, self::SOLAPA_TAMANO_MAX, self::SOLAPA_TAMANO_MIN, $this->medidaDe(), self::INTERLINEADO);

        return $this->parrafo($ajuste['lineas'], $panel->x + $margen, $panel->y + $margen + $ajuste['tamano'] * self::ASCENDENTE, $ajuste['tamano'], 'start', $libro->colores['texto'], [], $panel->ancho - 2 * $margen);
    }
}
