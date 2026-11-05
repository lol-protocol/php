<?php

namespace DefamatoryContentReview\Report;

/**
 * Frases legibles del informe (explicaciones, recomendaciones, categorías) en el idioma de salida.
 * Cada idioma es un `config/messages/<iso639-3>.php` con claves planas; `spa` es el origen y el
 * respaldo: una clave o un idioma que falta devuelve el texto en español, nunca una clave a medias.
 */
final class Messages
{
    public const DEFAULT_LOCALE = 'spa';

    /** @var array<string,array<string,string>> */
    private static array $tables = [];

    /** @param array<int,string|float> $args argumentos de sprintf */
    public static function get(string $key, string $locale, array $args = []): string
    {
        $text = self::find($key, $locale) ?? $key;

        return $args === [] ? $text : vsprintf($text, $args);
    }

    /** El texto de la clave en ese idioma, o en español; null si ninguno la tiene. */
    public static function find(string $key, string $locale): ?string
    {
        return self::table($locale)[$key] ?? self::table(self::DEFAULT_LOCALE)[$key] ?? null;
    }

    /** @return array<int,string> idiomas con mensajes propios, ordenados */
    public static function locales(): array
    {
        $codes = array_map(static fn(string $f): string => basename($f, '.php'), glob(self::dir() . '/*.php') ?: []);
        sort($codes);

        return $codes;
    }

    /** @return array<string,string> */
    public static function table(string $locale): array
    {
        $locale = strtolower($locale);
        if (!isset(self::$tables[$locale])) {
            $file = self::dir() . '/' . $locale . '.php';
            self::$tables[$locale] = preg_match('/^[a-z]{3}$/', $locale) === 1 && is_file($file) ? require $file : [];
        }

        return self::$tables[$locale];
    }

    private static function dir(): string
    {
        return dirname(__DIR__, 2) . '/config/messages';
    }
}
