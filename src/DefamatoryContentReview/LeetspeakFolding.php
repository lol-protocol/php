<?php

namespace DefamatoryContentReview;

/**
 * Sustitución numérica/simbólica compartida por los folders fonéticos, para
 * que una evasión combinada (fusión + leetspeak, p. ej. "Elb4"+"Gina") caiga
 * igual que la evasión simple ya cubierta en WordList::normalize().
 *
 * Mismo criterio que allí: no exhaustivo, y "1" se resuelve sólo como "i"
 * (su lectura más frecuente) en vez de intentar también "l".
 */
trait LeetspeakFolding
{
    private const LEETSPEAK = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
        '7' => 't', '8' => 'b', '@' => 'a', '$' => 's',
    ];

    private static function unleet(string $text): string
    {
        return strtr($text, self::LEETSPEAK);
    }
}
