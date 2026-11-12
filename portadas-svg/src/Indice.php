<?php

declare(strict_types=1);

namespace PortadasSvg;

/**
 * Hoja de contactos: un solo SVG (`indice.svg`) con la portada de cada libro en miniatura, su título y su autor, para
 * revisar de un vistazo un catálogo de cientos de libros. Es SVG puro, sin HTML ni JavaScript: cada portada se
 * refiere a su `portada.svg` por ruta relativa, así que el índice solo se ve bien al lado de las carpetas de los libros.
 */
final class Indice
{
    /** Columnas de la hoja y medidas de cada celda, en mm. */
    private const COLUMNAS = 6;
    private const CELDA_ANCHO = 44.0;
    private const MARGEN = 6.0;
    private const PIE = 14.0;
    private const TITULO_TAMANO = 3.4;
    private const AUTOR_TAMANO = 2.8;
    private const TITULO_MAXIMO = 26;

    /** @param list<Libro> $libros en el orden en que se muestran */
    public static function svg(array $libros): string
    {
        $celdaAncho = self::CELDA_ANCHO;
        $filas = (int) max(1, ceil(count($libros) / self::COLUMNAS));
        $ancho = self::COLUMNAS * ($celdaAncho + self::MARGEN) + self::MARGEN;

        // Cada fila es tan alta como su portada más alta.
        $altos = [];
        $cuerpo = '';
        foreach ($libros as $posicion => $libro) {
            $vista = (new Medidas($libro))->vista('portada');
            $altos[$posicion] = $celdaAncho * $vista->alto / $vista->ancho;
        }
        $y = self::MARGEN;
        for ($fila = 0; $fila < $filas; $fila++) {
            $grupo = array_slice($libros, $fila * self::COLUMNAS, self::COLUMNAS, true);
            $altoFila = 0.0;
            foreach ($libros as $posicion => $libro) {
                if (intdiv($posicion, self::COLUMNAS) === $fila) {
                    $altoFila = max($altoFila, $altos[$posicion]);
                }
            }
            foreach ($grupo as $posicion => $libro) {
                $x = self::MARGEN + ($posicion % self::COLUMNAS) * ($celdaAncho + self::MARGEN);
                $cuerpo .= Svg::elemento('image', [
                    'x' => $x, 'y' => $y, 'width' => $celdaAncho, 'height' => $altos[$posicion],
                    'preserveAspectRatio' => 'xMidYMin meet', 'xlink:href' => "{$libro->id}/portada.svg",
                ]);
                $cuerpo .= self::texto($libro->textos->titulo, $x, $y + $altos[$posicion] + self::TITULO_TAMANO + 1.0, self::TITULO_TAMANO, '#1d1b17', 'bold');
                $cuerpo .= self::texto($libro->textos->autor, $x, $y + $altos[$posicion] + self::TITULO_TAMANO + 1.0 + self::AUTOR_TAMANO + 1.0, self::AUTOR_TAMANO, '#5b574d', 'normal');
            }
            $y += $altoFila + self::PIE;
        }

        $vista = new Rectangulo(0.0, 0.0, $ancho, $y);
        $descripcion = count($libros) . ' portadas: ' . implode('; ', array_map(static fn (Libro $libro): string => "{$libro->textos->titulo}, {$libro->textos->autor}", array_slice($libros, 0, 10))) . (count($libros) > 10 ? '…' : '');

        return Svg::documento($vista, 'Índice de portadas', $descripcion, 'es', Svg::elemento('rect', ['x' => 0.0, 'y' => 0.0, 'width' => $ancho, 'height' => $y, 'fill' => '#ffffff']) . $cuerpo);
    }

    private static function texto(string $texto, float $x, float $y, float $tamano, string $color, string $peso): string
    {
        $corto = mb_strlen($texto) > self::TITULO_MAXIMO ? rtrim(mb_substr($texto, 0, self::TITULO_MAXIMO - 1)) . '…' : $texto;

        return Svg::elemento('text', ['x' => $x, 'y' => $y, 'font-family' => 'sans-serif', 'font-size' => $tamano, 'font-weight' => $peso, 'fill' => $color], Svg::e($corto));
    }
}
