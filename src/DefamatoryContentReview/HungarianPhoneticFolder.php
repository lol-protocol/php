<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el húngaro.
 *
 * El húngaro distingue vocales por calidad (a/á, e/é son pares donde el
 * acento cambia también el timbre, no sólo la duración — "á" no es
 * simplemente una "a" larga). Lo único que sí es puramente una marca de
 * duración sobre la misma calidad vocálica es el "acento doble": "ő" es la
 * versión larga de "ö", y "ű" la de "ü". Esos dos se pliegan a su pareja
 * corta porque son, fonéticamente, la misma vocal.
 *
 * La otra ambigüedad real y documentada del húngaro es "ly" vs. "j": hoy
 * se pronuncian exactamente igual (/j/), y cuál se escribe en cada
 * palabra es una convención histórica que se memoriza, no algo que el oído
 * resuelva — el mismo tipo de caso que "y"/"i" en checo y eslovaco. Se
 * pliegan al mismo símbolo.
 *
 * No se tocan á/é/í/ó/ú: son pares timbre+duración, no sólo duración, y
 * colapsarlos con su vocal corta uniría vocales que en húngaro suenan
 * distinto.
 */
class HungarianPhoneticFolder
{
    use LeetspeakFolding;

    private const ACCENTS = [
        'ő' => 'ö', 'ű' => 'ü',
    ];

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, self::ACCENTS);
        $text = preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos

        return str_replace('ly', 'j', $text);
    }
}
