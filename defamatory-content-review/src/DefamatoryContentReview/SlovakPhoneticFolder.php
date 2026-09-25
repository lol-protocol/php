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
class SlovakPhoneticFolder extends AbstractPhoneticFolder
{
    private const ACCENTS = [
        'á' => 'a', 'ä' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o',
        'ô' => 'o', 'ú' => 'u', 'ĺ' => 'l', 'ŕ' => 'r',
        'ý' => 'i', 'y' => 'i',
    ];

    /** @return array<string,string> */
    protected static function getAccents(): array { return self::ACCENTS; }

    protected static function applyLanguageRules(string $text): string { return $text; }
}
