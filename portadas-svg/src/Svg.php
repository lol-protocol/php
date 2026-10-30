<?php

declare(strict_types=1);

namespace PortadasSvg;

/** SVG escrito a mano, solo con lo que usan las plantillas. Todas las medidas son milímetros. */
final class Svg
{
    /**
     * Documento SVG del tamaño físico de `vista`, con el viewBox puesto sobre esa zona del lienzo.
     * `cuerpo` dibuja solo lo que cae dentro de la vista.
     *
     * Es accesible: se anuncia como una imagen (`role="img"`) con su título y su descripción, y declara el
     * idioma del texto. Un lector de pantalla no recorre el texto dibujado, así que la descripción lleva lo
     * que la pieza dice.
     */
    public static function documento(Rectangulo $vista, string $titulo, string $descripcion, string $idioma, string $cuerpo): string
    {
        $viewBox = implode(' ', [self::n($vista->x), self::n($vista->y), self::n($vista->ancho), self::n($vista->alto)]);
        $lang = self::e($idioma);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="' . self::n($vista->ancho) . 'mm" height="' . self::n($vista->alto) . 'mm" viewBox="' . $viewBox . '"'
            . ' role="img" aria-labelledby="titulo descripcion" lang="' . $lang . '" xml:lang="' . $lang . '">' . "\n"
            . '<title id="titulo">' . self::e($titulo) . "</title>\n"
            . '<desc id="descripcion">' . self::e($descripcion) . "</desc>\n"
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

    /**
     * Escapa texto para contenido y atributos XML, y quita lo que XML 1.0 no admite: casi todos los
     * caracteres de control y los dos no-caracteres U+FFFE y U+FFFF. El salto de página y el tabulador
     * vertical, típicos al copiar de un PDF, se leen como espacio. Un UTF-8 roto se sustituye por U+FFFD.
     */
    public static function e(string $texto): string
    {
        $texto = str_replace(["\x0B", "\x0C"], ' ', $texto);
        $escapado = htmlspecialchars($texto, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');

        return (string) preg_replace('/[\x00-\x08\x0E-\x1F\x{FFFE}\x{FFFF}]/u', '', $escapado);
    }

    /** Número con punto decimal y sin ceros sobrantes: 135, 4.8, 461.6. */
    public static function n(float $valor): string
    {
        $texto = rtrim(rtrim(number_format($valor, 3, '.', ''), '0'), '.');

        return $texto === '-0' ? '0' : $texto;
    }
}
