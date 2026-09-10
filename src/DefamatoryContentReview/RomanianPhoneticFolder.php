<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el rumano.
 *
 * "â" e "î" son la ambigüedad más conocida del rumano: representan
 * exactamente el mismo sonido (/ɨ/), y cuál se escribe depende sólo de la
 * posición en la palabra (â en medio, î al principio o después de
 * prefijo) — una regla ortográfica, no una diferencia de pronunciación. Se
 * pliegan al mismo símbolo.
 *
 * "ș"/"ş" (s con coma o con cedilla) y "ț"/"ţ" son la misma letra en dos
 * codificaciones distintas: fuentes y sistemas antiguos (Windows-1250,
 * bibliotecas sin soporte para el diacrítico correcto) representan la coma
 * suscrita como cedilla, así que ambas variantes aparecen en textos reales
 * para la misma letra. Se normalizan a una sola forma — esto no es una
 * aproximación fonética, es corregir una variación de codificación real.
 *
 * No se toca "ă": es una vocal con fonema propio (schwa), no una variante
 * de "a".
 */
class RomanianPhoneticFolder
{
    use LeetspeakFolding;

    private const NORMALIZE = [
        'î' => 'â', 'ş' => 'ș', 'ţ' => 'ț',
    ];

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, self::NORMALIZE);

        return preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos
    }
}
