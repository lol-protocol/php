<?php

declare(strict_types=1);

namespace PortadasSvg;

/** SVG escrito a mano, solo con lo que usan las plantillas. Todas las medidas son milímetros. */
final class Svg
{
    /**
     * Documento SVG del tamaño físico de `vista`. `cuerpo` dibuja el lienzo entero y el viewBox
     * recorta lo que queda fuera de la vista.
     */
    public static function documento(Rectangulo $vista, string $titulo, string $cuerpo): string
    {
        $viewBox = implode(' ', [self::n($vista->x), self::n($vista->y), self::n($vista->ancho), self::n($vista->alto)]);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" width="' . self::n($vista->ancho) . 'mm" height="' . self::n($vista->alto) . 'mm" viewBox="' . $viewBox . '">' . "\n"
            . '<title>' . self::e($titulo) . "</title>\n"
            . $cuerpo
            . "</svg>\n";
    }

    /** @param array<string, float|int|string> $atributos */
    public static function elemento(string $etiqueta, array $atributos, string $contenido = ''): string
    {
        $texto = '';
        foreach ($atributos as $nombre => $valor) {
            $texto .= ' ' . $nombre . '="' . self::e(is_string($valor) ? $valor : self::n((float) $valor)) . '"';
        }

        return $contenido === ''
            ? "<$etiqueta$texto/>\n"
            : "<$etiqueta$texto>$contenido</$etiqueta>\n";
    }

    /**
     * Una línea por `tspan`, con su propia x para que el anclaje (inicio, centro o fin) valga en cada una.
     *
     * @param list<string> $lineas
     */
    public static function lineas(array $lineas, float $x, float $tamano, float $interlineado): string
    {
        $salida = '';
        foreach ($lineas as $indice => $linea) {
            // Un tspan vacío no mueve el cursor de texto en los navegadores: la línea en blanco
            // lleva un espacio de no ruptura para que el salto se conserve.
            $salida .= self::elemento('tspan', [
                'x' => $x,
                'dy' => $indice === 0 ? 0.0 : $tamano * $interlineado,
            ], self::e($linea === '' ? "\u{00A0}" : $linea));
        }

        return $salida;
    }

    /** Escapa texto para contenido y atributos XML. */
    public static function e(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Número con punto decimal y sin ceros sobrantes: 135, 4.8, 461.6. */
    public static function n(float $valor): string
    {
        $texto = rtrim(rtrim(number_format($valor, 3, '.', ''), '0'), '.');

        return $texto === '-0' ? '0' : $texto;
    }
}
