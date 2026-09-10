<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el eslovaco.
 *
 * Comparte con el checo la misma ambigüedad "y"/"ý" frente a "i"/"í":
 * suenan igual, y cuál se escribe es una regla ortográfica histórica, no
 * algo que el oído distinga. Se pliegan al mismo símbolo.
 *
 * El resto de diacríticos vocálicos (á, é, í, ó, ú, ĺ, ŕ) marcan longitud,
 * fonémicamente relevante en eslovaco, así que sólo se les quita el acento
 * como normalización de accesibilidad — no se afirma que suenen igual a la
 * vocal corta. "ô" es un diptongo propio (/uo/) sin equivalente de una sola
 * letra; se aproxima a "o" por ser la vocal que más se le acerca visualmente
 * en teclados sin diacríticos eslovacos, práctica real y no una invención
 * fonética.
 */
class SlovakPhoneticFolder
{
    use LeetspeakFolding;

    private const ACCENTS = [
        'á' => 'a', 'ä' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o',
        'ô' => 'o', 'ú' => 'u', 'ĺ' => 'l', 'ŕ' => 'r',
        'ý' => 'i', 'y' => 'i',
    ];

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos

        return strtr($text, self::ACCENTS);
    }
}
