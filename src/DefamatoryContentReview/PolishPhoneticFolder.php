<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el polaco.
 *
 * Tres ambigüedades reales y bien documentadas — de las que se enseñan en
 * la escuela porque el oído no las resuelve, sólo la ortografía histórica:
 *
 * - "ó" y "u" suenan exactamente igual (/u/); cuál se escribe depende de
 *   la etimología, no del sonido. Se pliegan al mismo símbolo.
 * - "rz" y "ż" suenan exactamente igual (/ʐ/ o /ʂ/ según el contexto de
 *   sonoridad). Se unifican a la forma "rz" (no se colapsan con la "z"
 *   simple, que es un sonido distinto).
 * - "ch" y "h" suenan exactamente igual (/x/). Se unifican a la forma
 *   "ch".
 *
 * El resto de diacríticos (ą, ć, ę, ł, ń, ś, ź) marcan consonantes o
 * vocales nasales fonémicamente distintas de su base sin diacrítico — no
 * son ambigüedades de sonido, así que sólo se les quita el signo como
 * normalización de accesibilidad, igual que en los demás folders.
 */
class PolishPhoneticFolder
{
    use LeetspeakFolding;

    private const ACCENTS = [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ś' => 's', 'ź' => 'z',
    ];

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, self::ACCENTS);
        $text = preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos

        $text = str_replace('ż', 'rz', $text);

        // "ch" nativo se protege antes de convertir la "h" suelta, para no
        // duplicarla ("chleb" no debe pasar a "cchleb").
        $text = str_replace('ch', "\x01", $text);
        $text = str_replace('h', 'ch', $text);
        $text = str_replace("\x01", 'ch', $text);

        return str_replace('ó', 'u', $text);
    }
}
