<?php

declare(strict_types=1);

namespace PortadasSvg\Plantillas;

use PortadasSvg\Color;
use PortadasSvg\Libro;
use PortadasSvg\Mayusculas;
use PortadasSvg\Plantilla;
use PortadasSvg\Rectangulo;

/**
 * Libro ilustrado: la imagen del libro (`imagen`) ocupa la parte de arriba de la portada, a sangre, y debajo va una
 * franja con el título y el autor. Sin imagen, el emblema de la tradición (si la tiene) hace de ilustración.
 */
final class Ilustrada extends Plantilla
{
    /** Fracción del alto de la portada que ocupa la imagen. */
    private const IMAGEN_ALTO = 0.58;
    /** Emblema que sustituye a la imagen: radio (fracción del ancho) y trazo. */
    private const EMBLEMA_RADIO = 0.28;
    private const EMBLEMA_TRAZO = 0.5;
    /** Título: alto disponible bajo la imagen (fracción del alto de la portada) y tamaño. */
    private const TITULO_ALTO = 0.2;
    private const TITULO_ARRIBA = 6.0;
    private const TITULO_TAMANO_MAX = 11.0;
    private const TITULO_TAMANO_MIN = 6.0;
    /** Subtítulo: alto disponible y tamaño. */
    private const SUBTITULO_ALTO = 12.0;
    private const SUBTITULO_TAMANO_MAX = 4.2;
    /** Autor: distancia de su línea base al margen inferior, tamaño y espaciado entre letras. */
    private const AUTOR_ARRIBA_DEL_MARGEN = 4.0;
    private const AUTOR_TAMANO_MAX = 4.4;
    private const AUTOR_TAMANO_MIN = 3.0;
    private const AUTOR_ESPACIADO = 0.9;
    /** Línea de acento entre la imagen y el título: grosor. */
    private const FILETE_ALTO = 1.2;

    public function coloresPorDefecto(): array
    {
        return ['fondo' => '#f6f1e6', 'texto' => '#1d1b17', 'acento' => '#9a3b22'];
    }

    public function paleta(float $tono): array
    {
        return ['fondo' => Color::hsl($tono, 0.40, 0.92), 'texto' => Color::hsl($tono, 0.30, 0.12), 'acento' => Color::hsl($tono, 0.55, 0.32)];
    }

    protected function portada(Libro $libro, Rectangulo $panel): string
    {
        $colores = $libro->colores;
        $sangrado = $libro->formato->sangrado;
        $x = $panel->x + self::MARGEN;
        $ancho = $panel->ancho - 2 * self::MARGEN;
        $cx = $panel->x + $panel->ancho / 2;

        // La imagen llega al borde del papel, sangrado incluido, por arriba y por los lados que no son lomo.
        $alto = $panel->alto * self::IMAGEN_ALTO;
        $zona = new Rectangulo($panel->x, $panel->y - $sangrado, $panel->ancho + $sangrado, $alto + $sangrado);
        $svg = '';
        if ($libro->imagen !== null) {
            $svg .= $this->imagen($libro->imagen, $zona, 'xMidYMid slice');
        } else {
            $svg .= $this->rectangulo($zona, ['fill' => $colores['acento'], 'fill-opacity' => 0.12]);
            if ($libro->tradicion !== null) {
                $radio = $panel->ancho * self::EMBLEMA_RADIO;
                $svg .= $this->adorno($libro)->emblema($cx, $panel->y + $alto / 2, $radio, $colores['acento'], self::EMBLEMA_TRAZO);
            }
        }
        $svg .= $this->rectangulo(new Rectangulo($panel->x, $panel->y + $alto, $panel->ancho + $sangrado, self::FILETE_ALTO), ['fill' => $colores['acento']]);

        $abajo = $panel->y + $alto + self::FILETE_ALTO + self::TITULO_ARRIBA;
        $svg .= $this->tituloYSubtitulo(
            $libro, $cx, $ancho, 'middle', $abajo, $panel->alto * self::TITULO_ALTO, false,
            ['max' => self::TITULO_TAMANO_MAX, 'min' => self::TITULO_TAMANO_MIN, 'estilo' => [], 'color' => $colores['texto']],
            ['alto' => self::SUBTITULO_ALTO, 'max' => self::SUBTITULO_TAMANO_MAX, 'estilo' => ['font-style' => 'italic'], 'color' => $colores['texto']],
        );
        $svg .= $this->rotulo(Mayusculas::de($libro->textos->autor, $libro->idioma), $cx, $panel->y + $panel->alto - self::AUTOR_ARRIBA_DEL_MARGEN - self::MARGEN / 2, $ancho, self::AUTOR_TAMANO_MAX, self::AUTOR_TAMANO_MIN, 'middle', $colores['acento'], ['letter-spacing' => self::AUTOR_ESPACIADO]);

        return $svg;
    }
}
