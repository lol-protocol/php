<?php

namespace DefamatoryContentReview;

/**
 * Wiring compartido por los folders cuya única regla, más allá de leetspeak
 * y separadores (ver LeetspeakFolding), es una tabla de acentos: minúsculas,
 * unleet, sustituir por ACCENTS y quitar separadores — sin ninguna otra
 * transformación fonológica propia del idioma.
 *
 * Dos variantes según el orden que cada folder ya usaba entre sustituir
 * acentos y quitar separadores (ninguna ACCENTS de estos folders mapea un
 * separador, así que el orden no cambia el resultado — pero se conserva el
 * de cada uno en vez de forzarlos a coincidir).
 */
trait AccentOnlyPhoneticFolding
{
    use LeetspeakFolding;

    private static function foldAccentsThenSeparators(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, self::ACCENTS);

        return self::stripSeparators($text);
    }

    private static function foldSeparatorsThenAccents(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = self::stripSeparators($text);

        return strtr($text, self::ACCENTS);
    }
}
