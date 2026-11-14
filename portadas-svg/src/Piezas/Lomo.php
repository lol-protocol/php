<?php

declare(strict_types=1);

namespace PortadasSvg\Piezas;

use PortadasSvg\Adornos\Motivo;
use PortadasSvg\Fuentes;
use PortadasSvg\Libro;
use PortadasSvg\Rectangulo;
use PortadasSvg\Svg;
use PortadasSvg\Texto;


/**
 * El lomo de una plantilla: motivo de la categoría y título, autor y volumen girados 90°.
 *
 * Es parte de `Plantilla` (se usa desde ella) y se apoya en sus ayudas de texto y de dibujo.
 */
trait Lomo
{
    /** Lomo: ancho a partir del cual lleva texto, tamaño del texto (el máximo es también una fracción del ancho) y centrado. */
    /** Lomo: por debajo de este ancho no se escribe el texto. */
    public const LOMO_MINIMO = 3.0;
    protected const LOMO_TAMANO_MAX = 4.5;
    protected const LOMO_TAMANO_MIN = 2.2;
    protected const LOMO_TAMANO_ANCHO = 0.6;
    protected const LOMO_CENTRADO = 0.35;
    /** Lomo: el motivo de la categoría solo cabe si el lomo mide al menos esto; su radio (fracción del ancho del lomo) y el espacio libre hasta el texto. */
    protected const LOMO_MOTIVO_ANCHO_MINIMO = 5.0;
    protected const LOMO_MOTIVO_RADIO = 0.36;
    protected const LOMO_MOTIVO_SEPARACION = 3.0;

    protected function lomo(Libro $libro, Rectangulo $panel): string
    {
        $colores = $this->coloresLomo($libro);
        // El lomo no tiene borde exterior, así que su color llega también al sangrado de arriba y de abajo.
        $fondo = new Rectangulo($panel->x, 0.0, $panel->ancho, $panel->alto + 2 * $libro->formato->sangrado);
        $svg = $this->rectangulo($fondo, ['fill' => $colores['fondo']]);
        if ($panel->ancho < self::LOMO_MINIMO) {
            return $svg;
        }

        // Con categoría y un lomo ancho, el motivo va abajo. El texto deja el mismo espacio arriba y abajo,
        // para seguir centrado.
        $radio = $libro->categoria !== null && $panel->ancho >= self::LOMO_MOTIVO_ANCHO_MINIMO ? $panel->ancho * self::LOMO_MOTIVO_RADIO : 0.0;
        if ($radio > 0.0) {
            $svg .= Motivo::dibujar($libro->categoria ?? '', $panel->x + $panel->ancho / 2, $panel->y + $panel->alto - self::MARGEN - $radio, $radio, $colores['texto']);
        }
        $largo = $panel->alto - 2 * self::MARGEN - ($radio > 0.0 ? 2 * (2 * $radio + self::LOMO_MOTIVO_SEPARACION) : 0.0);
        $completo = implode(' · ', array_filter([$libro->textos->titulo, $libro->textos->autor, $this->rotuloDeVolumen($libro)], static fn (string $parte): bool => $parte !== ''));
        $texto = Texto::unaLinea($completo, $largo, min(self::LOMO_TAMANO_MAX, $panel->ancho * self::LOMO_TAMANO_ANCHO), self::LOMO_TAMANO_MIN, $this->medidaDe());

        // El texto va girado 90°. Al girarlo, la línea base queda en x y las letras crecen hacia un lado
        // u otro según el sentido; se corre x para que el texto quede centrado en el lomo.
        $sentido = $libro->lomoDireccion === 'abajo-arriba' ? -1 : 1;
        $x = $panel->x + $panel->ancho / 2 - $sentido * self::LOMO_CENTRADO * $texto['tamano'];
        $y = $panel->y + $panel->alto / 2;

        $this->usar([], $texto['texto']);

        if ($this->textoATrazos) {
            return $svg . $this->trazos()->escribir([$texto['texto']], 0.0, 0.0, $texto['tamano'], 'middle', $colores['texto'], $this->familia(), 'normal', 400, $this->medidaDe(), 0.0, self::INTERLINEADO, 'translate(' . Svg::n($x) . ' ' . Svg::n($y) . ') rotate(' . (90 * $sentido) . ') ');
        }

        return $svg . Svg::elemento('text', [
            'transform' => 'translate(' . Svg::n($x) . ' ' . Svg::n($y) . ') rotate(' . (90 * $sentido) . ')',
            'font-family' => Fuentes::pila($this->familia()),
            'font-size' => $texto['tamano'],
            'text-anchor' => 'middle',
            'fill' => $colores['texto'],
        ], Svg::e($texto['texto']));
    }
}
