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
    /** Lomo ancho (desde LOMO_MOTIVO_ANCHO_MINIMO): el número del volumen arriba, en grande (tamaño como fracción del ancho y máximo en mm), y el logo de la editorial abajo (lado como fracción del ancho y máximo en mm). */
    protected const LOMO_VOLUMEN_TAMANO = 0.55;
    protected const LOMO_VOLUMEN_TAMANO_MAX = 6.0;
    protected const LOMO_LOGO_LADO = 0.7;
    protected const LOMO_LOGO_LADO_MAX = 12.0;

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
        // Con un lomo ancho, el número del volumen va arriba en grande y el logo de la editorial abajo; el motivo sube
        // sobre el logo. El texto deja el mismo espacio arriba que abajo (el mayor de los dos), para seguir centrado.
        $ancho = $panel->ancho >= self::LOMO_MOTIVO_ANCHO_MINIMO;
        $centro = $panel->x + $panel->ancho / 2;
        $radio = $libro->categoria !== null && $ancho ? $panel->ancho * self::LOMO_MOTIVO_RADIO : 0.0;
        $insignia = $libro->textos->volumen !== '' && $ancho ? min($panel->ancho * self::LOMO_VOLUMEN_TAMANO, self::LOMO_VOLUMEN_TAMANO_MAX) : 0.0;
        $logo = $libro->logo !== null && $ancho ? min($panel->ancho * self::LOMO_LOGO_LADO, self::LOMO_LOGO_LADO_MAX) : 0.0;
        $fondoDelLomo = $panel->y + $panel->alto - self::MARGEN;
        if ($logo > 0.0 && $libro->logo !== null) {
            $svg .= $this->imagen($libro->logo, new Rectangulo($centro - $logo / 2, $fondoDelLomo - $logo, $logo, $logo), 'xMidYMid meet');
            $fondoDelLomo -= $logo + self::LOMO_MOTIVO_SEPARACION;
        }
        if ($radio > 0.0) {
            $svg .= Motivo::dibujar($libro->categoria ?? '', $centro, $fondoDelLomo - $radio, $radio, $colores['texto']);
        }
        if ($insignia > 0.0) {
            $svg .= $this->parrafo([$libro->textos->volumen], $centro, $panel->y + self::MARGEN + $insignia * self::ASCENDENTE, $insignia, 'middle', $colores['texto'], $this->familia() === 'sans' ? ['font-weight' => 'bold'] : []);
        }
        $abajo = ($radio > 0.0 ? 2 * $radio + self::LOMO_MOTIVO_SEPARACION : 0.0) + ($logo > 0.0 ? $logo + self::LOMO_MOTIVO_SEPARACION : 0.0);
        $arriba = $insignia > 0.0 ? $insignia + self::LOMO_MOTIVO_SEPARACION : 0.0;
        $largo = $panel->alto - 2 * self::MARGEN - 2 * max($abajo, $arriba);
        // Si el volumen va en la insignia, no se repite en el texto girado.
        $volumen = $insignia > 0.0 ? '' : $this->rotuloDeVolumen($libro);
        $completo = implode(' · ', array_filter([$libro->textos->titulo, $libro->textos->autor, $volumen], static fn (string $parte): bool => $parte !== ''));
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
