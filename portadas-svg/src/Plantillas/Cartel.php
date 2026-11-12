<?php

declare(strict_types=1);

namespace PortadasSvg\Plantillas;

use PortadasSvg\Color;
use PortadasSvg\Frases;
use PortadasSvg\Libro;
use PortadasSvg\Mayusculas;
use PortadasSvg\Plantilla;
use PortadasSvg\Rectangulo;
use PortadasSvg\Texto;

/**
 * Libro en cartel: un título enorme en negrita, en mayúsculas, sobre el color de acento a todo lo ancho, y el autor
 * debajo. Sin adornos de tradición: lo único que dibuja es letra.
 */
final class Cartel extends Plantilla
{
    /** Banda de acento: distancia al borde superior del corte y fracción del alto que ocupa. */
    private const BANDA_Y = 0.16;
    private const BANDA_ALTO = 0.5;
    /** Título dentro de la banda: margen interior y tamaño. */
    private const TITULO_MARGEN = 8.0;
    private const TITULO_TAMANO_MAX = 22.0;
    private const TITULO_TAMANO_MIN = 8.0;
    /** Subtítulo bajo la banda: alto disponible y tamaño. */
    private const SUBTITULO_ARRIBA = 8.0;
    private const SUBTITULO_TAMANO_MAX = 5.0;
    /** Autor: distancia de su línea base al margen inferior, tamaño y espaciado entre letras. */
    private const AUTOR_ARRIBA_DEL_MARGEN = 6.0;
    private const AUTOR_TAMANO_MAX = 6.0;
    private const AUTOR_TAMANO_MIN = 3.4;
    private const AUTOR_ESPACIADO = 1.6;
    /** Traductor: separación bajo el autor y tamaño. */
    private const TRADUCTOR_SEPARACION = 6.0;
    private const TRADUCTOR_TAMANO_MAX = 3.4;
    private const TRADUCTOR_TAMANO_MIN = 2.4;

    /** El cartel es solo letra: no dibuja la imagen del libro. */
    public function dibujaImagen(): bool
    {
        return false;
    }

    public function coloresPorDefecto(): array
    {
        return ['fondo' => '#f4f1ea', 'texto' => '#16161a', 'acento' => '#e8452c'];
    }

    public function paleta(float $tono): array
    {
        return ['fondo' => Color::hsl($tono, 0.30, 0.94), 'texto' => Color::hsl($tono, 0.30, 0.10), 'acento' => Color::hsl($tono, 0.70, 0.42)];
    }

    public function familia(): string
    {
        return 'sans';
    }

    protected function portada(Libro $libro, Rectangulo $panel): string
    {
        $colores = $libro->colores;
        $sangrado = $libro->formato->sangrado;
        $x = $panel->x + self::MARGEN;
        $ancho = $panel->ancho - 2 * self::MARGEN;

        // La banda llega a los dos bordes del papel. El título se pinta con el color del fondo para que se lea sobre el acento.
        $banda = new Rectangulo($panel->x, $panel->y + $panel->alto * self::BANDA_Y, $panel->ancho + $sangrado, $panel->alto * self::BANDA_ALTO);
        $svg = $this->rectangulo($banda, ['fill' => $colores['acento']]);
        $colorDelTitulo = Color::contraste($colores['acento'], $colores['fondo']) >= Color::contraste($colores['acento'], $colores['texto']) ? $colores['fondo'] : $colores['texto'];
        $estilo = ['font-weight' => 'bold'];
        $alto = $banda->alto - 2 * self::TITULO_MARGEN;
        $titulo = Texto::ajustar($libro->textos->titulo, $ancho, $alto, self::TITULO_TAMANO_MAX, self::TITULO_TAMANO_MIN, $this->medidaDe($estilo), self::INTERLINEADO, true);
        $svg .= $this->parrafo($titulo['lineas'], $x, $this->baseline($banda->y + self::TITULO_MARGEN, $alto, count($titulo['lineas']), $titulo['tamano']), $titulo['tamano'], 'start', $colorDelTitulo, $estilo);

        return $svg . $this->pie($libro, $panel, $x, $ancho, $banda);
    }

    private function pie(Libro $libro, Rectangulo $panel, float $x, float $ancho, Rectangulo $banda): string
    {
        $colores = $libro->colores;
        $svg = '';
        if ($libro->textos->subtitulo !== '') {
            $arriba = $banda->y + $banda->alto + self::SUBTITULO_ARRIBA;
            $svg .= $this->rotulo($libro->textos->subtitulo, $x, $arriba + self::SUBTITULO_TAMANO_MAX * self::ASCENDENTE, $ancho, self::SUBTITULO_TAMANO_MAX, 3.2, 'start', $colores['texto']);
        }
        $autorY = $panel->y + $panel->alto - self::MARGEN - self::AUTOR_ARRIBA_DEL_MARGEN;
        $svg .= $this->rotulo(Mayusculas::de($libro->textos->autor, $libro->idioma), $x, $autorY, $ancho, self::AUTOR_TAMANO_MAX, self::AUTOR_TAMANO_MIN, 'start', $colores['texto'], ['letter-spacing' => self::AUTOR_ESPACIADO, 'font-weight' => 'bold']);
        if ($libro->textos->traductor !== '') {
            $svg .= $this->rotulo(Frases::texto($libro->idioma, 'traduccion_rotulo', ['traductor' => $libro->textos->traductor]), $x, $autorY + self::TRADUCTOR_SEPARACION, $ancho, self::TRADUCTOR_TAMANO_MAX, self::TRADUCTOR_TAMANO_MIN, 'start', $colores['texto']);
        }

        return $svg . $this->editorial($libro, $x, $panel->y + $panel->alto - self::MARGEN / 2 + 2.0, 'start', $ancho);
    }
}
