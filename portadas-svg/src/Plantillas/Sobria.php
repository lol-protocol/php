<?php

declare(strict_types=1);

namespace PortadasSvg\Plantillas;

use PortadasSvg\Libro;
use PortadasSvg\Plantilla;
use PortadasSvg\Rectangulo;
use PortadasSvg\Texto;

/** Libro sobrio: fondo oscuro, sans, título a la izquierda y lomo en el color de acento. */
final class Sobria extends Plantilla
{
    /** Barra de acento sobre el título: distancia al borde superior del corte, ancho y alto. */
    private const BARRA_Y = 14.0;
    private const BARRA_ANCHO = 18.0;
    private const BARRA_ALTO = 2.4;
    /** Título: distancia al borde superior del corte, alto disponible (fracción del alto) y tamaño. */
    private const TITULO_Y = 30.0;
    private const TITULO_ALTO = 0.42;
    private const TITULO_TAMANO_MAX = 16.0;
    private const TITULO_TAMANO_MIN = 8.0;
    /** Subtítulo: alto disponible y tamaño. */
    private const SUBTITULO_ALTO = 20.0;
    private const SUBTITULO_TAMANO_MAX = 5.0;
    private const SUBTITULO_TAMANO_MIN = 3.2;
    /** Autor: distancia de su línea base al margen inferior, tamaño y espaciado entre letras. */
    private const AUTOR_ARRIBA_DEL_MARGEN = 12.0;
    private const AUTOR_TAMANO_MAX = 5.2;
    private const AUTOR_TAMANO_MIN = 3.4;
    private const AUTOR_ESPACIADO = 1.2;
    /** Regla de acento sobre el autor: separación, ancho y alto. */
    private const REGLA_SEPARACION = 9.0;
    private const REGLA_ANCHO = 12.0;
    private const REGLA_ALTO = 0.8;
    /** Traductor: separación bajo el autor y tamaño. */
    private const TRADUCTOR_SEPARACION = 7.0;
    private const TRADUCTOR_TAMANO_MAX = 3.4;
    private const TRADUCTOR_TAMANO_MIN = 2.4;
    /** Pie con la editorial: cuánto baja bajo el margen inferior. */
    private const PIE_BAJO_EL_MARGEN = 4.0;

    public function coloresPorDefecto(): array
    {
        return ['fondo' => '#1c1f24', 'texto' => '#f2efe6', 'acento' => '#c8a24a'];
    }

    public function familia(): string
    {
        return 'sans';
    }

    protected function coloresLomo(Libro $libro): array
    {
        return ['fondo' => $libro->colores['acento'], 'texto' => $libro->colores['fondo']];
    }

    protected function portada(Libro $libro, Rectangulo $panel): string
    {
        $colores = $libro->colores;
        $x = $panel->x + self::MARGEN;
        $ancho = $panel->ancho - 2 * self::MARGEN;

        $svg = $this->rectangulo(new Rectangulo($x, $panel->y + self::BARRA_Y, self::BARRA_ANCHO, self::BARRA_ALTO), ['fill' => $colores['acento']]);

        $titulo = Texto::ajustar($libro->textos->titulo, $ancho, $panel->alto * self::TITULO_ALTO, self::TITULO_TAMANO_MAX, self::TITULO_TAMANO_MIN, $this->medidaDe(['font-weight' => 'bold']), self::INTERLINEADO, true);
        $y = $panel->y + self::TITULO_Y + $titulo['tamano'] * self::ASCENDENTE;
        $svg .= $this->parrafo($titulo['lineas'], $x, $y, $titulo['tamano'], 'start', $colores['texto'], ['font-weight' => 'bold']);
        $fin = $y + (count($titulo['lineas']) - 1) * $titulo['tamano'] * self::INTERLINEADO;

        if ($libro->textos->subtitulo !== '') {
            $subtitulo = Texto::ajustar($libro->textos->subtitulo, $ancho, self::SUBTITULO_ALTO, self::SUBTITULO_TAMANO_MAX, self::SUBTITULO_TAMANO_MIN, $this->medidaDe(), self::INTERLINEADO, true);
            $svg .= $this->parrafo($subtitulo['lineas'], $x, $this->bajoElTitulo($fin, $titulo['tamano'], $subtitulo['tamano']), $subtitulo['tamano'], 'start', $colores['acento']);
        }

        // Abajo: una regla de acento, el autor en mayúsculas espaciadas, el traductor y la editorial.
        $autorY = $panel->y + $panel->alto - self::MARGEN - self::AUTOR_ARRIBA_DEL_MARGEN;
        $svg .= $this->rectangulo(new Rectangulo($x, $autorY - self::REGLA_SEPARACION, self::REGLA_ANCHO, self::REGLA_ALTO), ['fill' => $colores['acento']]);
        $svg .= $this->rotulo(mb_strtoupper($libro->textos->autor), $x, $autorY, $ancho, self::AUTOR_TAMANO_MAX, self::AUTOR_TAMANO_MIN, 'start', $colores['texto'], ['letter-spacing' => self::AUTOR_ESPACIADO]);
        if ($libro->textos->traductor !== '') {
            $svg .= $this->rotulo('Traducción de ' . $libro->textos->traductor, $x, $autorY + self::TRADUCTOR_SEPARACION, $ancho, self::TRADUCTOR_TAMANO_MAX, self::TRADUCTOR_TAMANO_MIN, 'start', $colores['texto']);
        }
        $svg .= $this->editorial($libro, $x, $panel->y + $panel->alto - self::MARGEN + self::PIE_BAJO_EL_MARGEN, 'start', $ancho);

        return $svg;
    }
}
