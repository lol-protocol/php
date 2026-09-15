<?php

namespace DefamatoryContentReview;

/**
 * Conveniencia para que cada folder fonético siga llamando `self::unleet()`
 * sin cambiar cada sitio de uso. El mapa en sí vive en Leetspeak (una clase,
 * no un trait: las constantes de trait exigen PHP 8.2+).
 */
trait LeetspeakFolding
{
    private static function unleet(string $text): string
    {
        return Leetspeak::unleet($text);
    }
}
