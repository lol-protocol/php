<?php

declare(strict_types=1);

namespace PortadasSvg\Plantillas;

use PortadasSvg\Color;
use PortadasSvg\Libro;
use PortadasSvg\Plantilla;
use PortadasSvg\Rectangulo;
use PortadasSvg\Texto;

/**
 * Libro tipográfico: sin marco, el autor y el título a la izquierda en serif, y el emblema de la tradición
 * en grande, de línea fina, ocupando la mitad de abajo.
 */
final class Tipografica extends Plantilla
{
    /** Barra de acento sobre el autor: distancia al borde superior del corte, ancho y alto. */
    private const BARRA_Y = 16.0;
    private const BARRA_ANCHO = 14.0;
    private const BARRA_ALTO = 1.2;
    /** Autor: distancia de su línea base al borde superior, tamaño y espaciado entre letras. */
    private const AUTOR_Y = 28.0;
    private const AUTOR_TAMANO_MAX = 4.4;
    private const AUTOR_TAMANO_MIN = 3.2;
    private const AUTOR_ESPACIADO = 1.0;
    /** Título: distancia al borde superior del corte, alto disponible (fracción del alto) y tamaño. */
    private const TITULO_Y = 36.0;
    private const TITULO_ALTO = 0.28;
    private const TITULO_TAMANO_MAX = 20.0;
    private const TITULO_TAMANO_MIN = 8.0;
    /** Subtítulo: alto disponible y tamaño. */
    private const SUBTITULO_ALTO = 16.0;
    private const SUBTITULO_TAMANO_MAX = 5.2;
    private const SUBTITULO_TAMANO_MIN = 3.2;
    /** Traductor: distancia de su línea base al borde inferior del emblema, y tamaño. */
    private const TRADUCTOR_TAMANO_MAX = 3.4;
    private const TRADUCTOR_TAMANO_MIN = 2.4;
    /** Emblema: altura de su centro (fracción del alto), radio máximo y grosor de la línea en mm. */
    private const EMBLEMA_Y = 0.73;
    private const EMBLEMA_RADIO = 34.0;
    private const EMBLEMA_TRAZO = 0.3;
    /** Pie con la editorial: distancia de su línea base al borde inferior del corte. */
    private const PIE_ABAJO = 14.0;

    public function coloresPorDefecto(): array
    {
        return ['fondo' => '#f7f2e8', 'texto' => '#1f2a30', 'acento' => '#9a3b26'];
    }

    public function paleta(float $tono): array
    {
        return ['fondo' => Color::hsl($tono, 0.35, 0.94), 'texto' => Color::hsl($tono, 0.40, 0.12), 'acento' => Color::hsl($tono, 0.58, 0.34)];
    }

    protected function portada(Libro $libro, Rectangulo $panel): string
    {
        $colores = $libro->colores;
        $x = $panel->x + self::MARGEN;
        $ancho = $panel->ancho - 2 * self::MARGEN;

        $svg = $this->rectangulo(new Rectangulo($x, $panel->y + self::BARRA_Y, self::BARRA_ANCHO, self::BARRA_ALTO), ['fill' => $colores['acento']]);
        $svg .= $this->rotulo(mb_strtoupper($libro->textos->autor), $x, $panel->y + self::AUTOR_Y, $ancho, self::AUTOR_TAMANO_MAX, self::AUTOR_TAMANO_MIN, 'start', $colores['acento'], ['letter-spacing' => self::AUTOR_ESPACIADO]);

        $titulo = Texto::ajustar($libro->textos->titulo, $ancho, $panel->alto * self::TITULO_ALTO, self::TITULO_TAMANO_MAX, self::TITULO_TAMANO_MIN, $this->medidaDe(), self::INTERLINEADO, true);
        $y = $panel->y + self::TITULO_Y + $titulo['tamano'] * self::ASCENDENTE;
        $svg .= $this->parrafo($titulo['lineas'], $x, $y, $titulo['tamano'], 'start', $colores['texto']);
        $fin = $y + (count($titulo['lineas']) - 1) * $titulo['tamano'] * self::INTERLINEADO;

        if ($libro->textos->subtitulo !== '') {
            $subtitulo = Texto::ajustar($libro->textos->subtitulo, $ancho, self::SUBTITULO_ALTO, self::SUBTITULO_TAMANO_MAX, self::SUBTITULO_TAMANO_MIN, $this->medidaDe(['font-style' => 'italic']), self::INTERLINEADO, true);
            $svg .= $this->parrafo($subtitulo['lineas'], $x, $this->bajoElTitulo($fin, $titulo['tamano'], $subtitulo['tamano']), $subtitulo['tamano'], 'start', $colores['texto'], ['font-style' => 'italic']);
        }

        // Abajo, el emblema de la tradición en grande y de línea fina, centrado.
        $radio = min(self::EMBLEMA_RADIO, $ancho / 2);
        $svg .= $this->adorno($libro)->emblema($panel->x + $panel->ancho / 2, $panel->y + $panel->alto * self::EMBLEMA_Y, $radio, $colores['acento'], self::EMBLEMA_TRAZO);

        if ($libro->textos->traductor !== '') {
            $svg .= $this->rotulo('Traducción de ' . $libro->textos->traductor, $x, $panel->y + $panel->alto - self::PIE_ABAJO - 6.0, $ancho, self::TRADUCTOR_TAMANO_MAX, self::TRADUCTOR_TAMANO_MIN, 'start', $colores['texto'], ['font-style' => 'italic']);
        }
        $svg .= $this->editorial($libro, $x, $panel->y + $panel->alto - self::PIE_ABAJO, 'start', $ancho);

        return $svg;
    }
}
