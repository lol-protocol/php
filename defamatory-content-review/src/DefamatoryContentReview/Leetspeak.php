<?php

namespace DefamatoryContentReview;

/**
 * Sustitución numérica/simbólica compartida por WordList::normalize() y por
 * todos los folders fonéticos (vía LeetspeakFolding), para que una evasión
 * combinada ("Elb4"+"Gina") caiga igual que la simple.
 *
 * No exhaustivo: "1" se resuelve sólo como "i" (su lectura más frecuente),
 * sin intentar también "l" — una sustitución ambigua entre dos letras no
 * puede resolverse sin contexto.
 *
 * Clase, no trait: una constante dentro de un trait requiere PHP 8.2+, y
 * este proyecto soporta 8.1.
 */
final class Leetspeak
{
    private const MAP = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
        '7' => 't', '8' => 'b', '@' => 'a', '$' => 's',
    ];

    public static function unleet(string $text): string
    {
        return strtr($text, self::MAP);
    }
}
