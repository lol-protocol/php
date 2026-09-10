<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el neerlandés.
 *
 * Dos pares de dígrafos son homófonos reales y muy conocidos en neerlandés
 * — de los que se enseñan explícitamente en la escuela porque no hay forma
 * de distinguirlos de oído:
 *
 * - "ei" e "ij" suenan exactamente igual (/ɛi/). Es la confusión
 *   ortográfica más famosa del idioma.
 * - "au" y "ou" suenan exactamente igual (/ʌu/).
 *
 * Ambos pares se unifican a una de sus dos formas. No se modela ninguna
 * otra confusión: fuera de esos dos pares, la ortografía neerlandesa es
 * bastante regular.
 */
class DutchPhoneticFolder
{
    use LeetspeakFolding;

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos

        $text = str_replace('ij', 'ei', $text);

        return str_replace('ou', 'au', $text);
    }
}
