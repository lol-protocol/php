<?php

declare(strict_types=1);

namespace PortadasSvg\Plantillas;

use PortadasSvg\Libro;
use PortadasSvg\Plantilla;
use PortadasSvg\Rectangulo;
use PortadasSvg\Svg;
use PortadasSvg\Texto;

/** Libro clásico: marco doble, título centrado en serif y un rombo entre el título y el autor. */
final class Clasica extends Plantilla
{
    public function coloresPorDefecto(): array
    {
        return ['fondo' => '#f3ead7', 'texto' => '#2a1f17', 'acento' => '#7a2e1f'];
    }

    protected function portada(Libro $libro, Rectangulo $panel): string
    {
        $colores = $libro->colores;
        $cx = $panel->x + $panel->ancho / 2;
        $ancho = $panel->ancho - 2 * self::MARGEN;

        $svg = $this->rectangulo(new Rectangulo($panel->x + 8, $panel->y + 8, $panel->ancho - 16, $panel->alto - 16), ['fill' => 'none', 'stroke' => $colores['acento'], 'stroke-width' => 0.5]);
        $svg .= $this->rectangulo(new Rectangulo($panel->x + 10.5, $panel->y + 10.5, $panel->ancho - 21, $panel->alto - 21), ['fill' => 'none', 'stroke' => $colores['acento'], 'stroke-width' => 0.2]);

        $zonaAlto = $panel->alto * 0.36;
        $titulo = Texto::ajustar($libro->titulo, $ancho, $zonaAlto, 13.0, 7.0, $this->factorAncho(), self::INTERLINEADO);
        $y = $this->baseline($panel->y + $panel->alto * 0.14, $zonaAlto, count($titulo['lineas']), $titulo['tamano']);
        $svg .= $this->parrafo($titulo['lineas'], $cx, $y, $titulo['tamano'], 'middle', $colores['texto']);
        $fin = $y + (count($titulo['lineas']) - 1) * $titulo['tamano'] * self::INTERLINEADO;

        if ($libro->subtitulo !== '') {
            $subtitulo = Texto::ajustar($libro->subtitulo, $ancho, 16.0, 5.0, 3.2, $this->factorAncho(), self::INTERLINEADO);
            $svg .= $this->parrafo($subtitulo['lineas'], $cx, $fin + 9, $subtitulo['tamano'], 'middle', $colores['texto'], ['font-style' => 'italic']);
        }

        $ornamento = $panel->y + $panel->alto * 0.6;
        $svg .= $this->linea($cx - 16, $ornamento, $cx - 4, $ornamento, $colores['acento'], 0.4);
        $svg .= $this->linea($cx + 4, $ornamento, $cx + 16, $ornamento, $colores['acento'], 0.4);
        $svg .= Svg::elemento('polygon', ['points' => $this->rombo($cx, $ornamento, 2.2), 'fill' => $colores['acento']]);

        if ($libro->traductor !== '') {
            $svg .= $this->parrafo(['Traducción de ' . $libro->traductor], $cx, $ornamento + 9, 3.4, 'middle', $colores['texto'], ['font-style' => 'italic']);
        }
        $svg .= $this->parrafo([mb_strtoupper($libro->autor)], $cx, $panel->y + $panel->alto * 0.8, 5.6, 'middle', $colores['texto'], ['letter-spacing' => 0.9]);
        $svg .= $this->editorial($libro, $cx, $panel->y + $panel->alto - 18, 'middle');

        return $svg;
    }

    /** Puntos de un rombo centrado en (cx, cy), para el ornamento. */
    private function rombo(float $cx, float $cy, float $radio): string
    {
        $punto = static fn (float $x, float $y): string => Svg::n($x) . ',' . Svg::n($y);

        return implode(' ', [
            $punto($cx, $cy - $radio),
            $punto($cx + $radio, $cy),
            $punto($cx, $cy + $radio),
            $punto($cx - $radio, $cy),
        ]);
    }
}
