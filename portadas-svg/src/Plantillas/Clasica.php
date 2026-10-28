<?php

declare(strict_types=1);

namespace PortadasSvg\Plantillas;

use PortadasSvg\Libro;
use PortadasSvg\Plantilla;
use PortadasSvg\Color;
use PortadasSvg\Rectangulo;
use PortadasSvg\Texto;

/** Libro clásico: marco doble, título centrado en serif y un rombo entre el título y el autor. */
final class Clasica extends Plantilla
{
    /** Marco doble: distancia de cada línea al borde del corte, y su grosor. */
    private const MARCO_EXTERIOR = 8.0;
    private const MARCO_INTERIOR = 10.5;
    private const MARCO_EXTERIOR_GROSOR = 0.5;
    private const MARCO_INTERIOR_GROSOR = 0.2;
    /** Título: zona vertical (fracciones del alto del corte) y tamaño. */
    private const TITULO_Y = 0.14;
    private const TITULO_ALTO = 0.36;
    private const TITULO_TAMANO_MAX = 13.0;
    private const TITULO_TAMANO_MIN = 7.0;
    /** Subtítulo: alto disponible y tamaño. */
    private const SUBTITULO_ALTO = 16.0;
    private const SUBTITULO_TAMANO_MAX = 5.0;
    private const SUBTITULO_TAMANO_MIN = 3.2;
    /** Friso del adorno de la tradición, arriba dentro del marco: distancia al borde superior del corte, margen a cada lado y alto. */
    private const FRISO_Y = 17.0;
    private const FRISO_MARGEN = 18.0;
    private const FRISO_ALTO = 5.0;
    /** Ornamento: altura (fracción del alto), radio del emblema, y tramos de línea a cada lado (separación del emblema, largo hasta, grosor). */
    private const ORNAMENTO_Y = 0.6;
    private const EMBLEMA_RADIO = 4.4;
    private const ORNAMENTO_LINEA_SEPARACION = 1.8;
    private const ORNAMENTO_LINEA_HASTA = 16.0;
    private const ORNAMENTO_LINEA_GROSOR = 0.4;
    /** Traductor: separación bajo el ornamento y tamaño. */
    private const TRADUCTOR_SEPARACION = 9.0;
    private const TRADUCTOR_TAMANO_MAX = 3.4;
    private const TRADUCTOR_TAMANO_MIN = 2.4;
    /** Autor: altura (fracción del alto), tamaño y espaciado entre letras. */
    private const AUTOR_Y = 0.8;
    private const AUTOR_TAMANO_MAX = 5.6;
    private const AUTOR_TAMANO_MIN = 3.6;
    private const AUTOR_ESPACIADO = 0.9;
    /** Pie con la editorial: distancia de su línea base al borde inferior del corte. */
    private const PIE_ABAJO = 18.0;

    public function coloresPorDefecto(): array
    {
        return ['fondo' => '#f3ead7', 'texto' => '#2a1f17', 'acento' => '#7a2e1f'];
    }

    public function paleta(float $tono): array
    {
        return ['fondo' => Color::hsl($tono, 0.45, 0.91), 'texto' => Color::hsl($tono, 0.35, 0.13), 'acento' => Color::hsl($tono, 0.55, 0.33)];
    }

    protected function portada(Libro $libro, Rectangulo $panel): string
    {
        $colores = $libro->colores;
        $adorno = $this->adorno($libro);
        $cx = $panel->x + $panel->ancho / 2;
        $ancho = $panel->ancho - 2 * self::MARGEN;

        $exterior = self::MARCO_EXTERIOR;
        $interior = self::MARCO_INTERIOR;
        $svg = $this->rectangulo(new Rectangulo($panel->x + $exterior, $panel->y + $exterior, $panel->ancho - 2 * $exterior, $panel->alto - 2 * $exterior), ['fill' => 'none', 'stroke' => $colores['acento'], 'stroke-width' => self::MARCO_EXTERIOR_GROSOR]);
        $svg .= $this->rectangulo(new Rectangulo($panel->x + $interior, $panel->y + $interior, $panel->ancho - 2 * $interior, $panel->alto - 2 * $interior), ['fill' => 'none', 'stroke' => $colores['acento'], 'stroke-width' => self::MARCO_INTERIOR_GROSOR]);

        $svg .= $adorno->friso(new Rectangulo($panel->x + self::FRISO_MARGEN, $panel->y + self::FRISO_Y, $panel->ancho - 2 * self::FRISO_MARGEN, self::FRISO_ALTO), $colores['acento']);

        $zonaAlto = $panel->alto * self::TITULO_ALTO;
        $titulo = Texto::ajustar($libro->textos->titulo, $ancho, $zonaAlto, self::TITULO_TAMANO_MAX, self::TITULO_TAMANO_MIN, $this->medidaDe(), self::INTERLINEADO, true);
        $y = $this->baseline($panel->y + $panel->alto * self::TITULO_Y, $zonaAlto, count($titulo['lineas']), $titulo['tamano']);
        $svg .= $this->parrafo($titulo['lineas'], $cx, $y, $titulo['tamano'], 'middle', $colores['texto']);
        $fin = $y + (count($titulo['lineas']) - 1) * $titulo['tamano'] * self::INTERLINEADO;

        if ($libro->textos->subtitulo !== '') {
            $subtitulo = Texto::ajustar($libro->textos->subtitulo, $ancho, self::SUBTITULO_ALTO, self::SUBTITULO_TAMANO_MAX, self::SUBTITULO_TAMANO_MIN, $this->medidaDe(['font-style' => 'italic']), self::INTERLINEADO, true);
            $svg .= $this->parrafo($subtitulo['lineas'], $cx, $this->bajoElTitulo($fin, $titulo['tamano'], $subtitulo['tamano']), $subtitulo['tamano'], 'middle', $colores['texto'], ['font-style' => 'italic']);
        }

        $ornamento = $panel->y + $panel->alto * self::ORNAMENTO_Y;
        $desde = self::EMBLEMA_RADIO * $adorno->ocupa() + self::ORNAMENTO_LINEA_SEPARACION;
        $svg .= $this->linea($cx - self::ORNAMENTO_LINEA_HASTA, $ornamento, $cx - $desde, $ornamento, $colores['acento'], self::ORNAMENTO_LINEA_GROSOR);
        $svg .= $this->linea($cx + $desde, $ornamento, $cx + self::ORNAMENTO_LINEA_HASTA, $ornamento, $colores['acento'], self::ORNAMENTO_LINEA_GROSOR);
        $svg .= $adorno->emblema($cx, $ornamento, self::EMBLEMA_RADIO, $colores['acento']);

        if ($libro->textos->traductor !== '') {
            $svg .= $this->rotulo('Traducción de ' . $libro->textos->traductor, $cx, $ornamento + self::TRADUCTOR_SEPARACION, $ancho, self::TRADUCTOR_TAMANO_MAX, self::TRADUCTOR_TAMANO_MIN, 'middle', $colores['texto'], ['font-style' => 'italic']);
        }
        $svg .= $this->rotulo(mb_strtoupper($libro->textos->autor), $cx, $panel->y + $panel->alto * self::AUTOR_Y, $ancho, self::AUTOR_TAMANO_MAX, self::AUTOR_TAMANO_MIN, 'middle', $colores['texto'], ['letter-spacing' => self::AUTOR_ESPACIADO]);
        $svg .= $this->editorial($libro, $cx, $panel->y + $panel->alto - self::PIE_ABAJO, 'middle', $ancho);

        return $svg;
    }
}
