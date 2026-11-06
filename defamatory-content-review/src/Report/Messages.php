<?php

namespace DefamatoryContentReview\Report;

use DefamatoryContentReview\Language\LanguageRegistry;

/**
 * Frases legibles del informe (explicaciones, recomendaciones, categorías, errores del endpoint) en el idioma
 * de salida. Cada idioma es un `<carpeta>/<iso639-3>.php` con claves planas (por omisión `config/messages/`);
 * `spa` es el origen y el respaldo: una clave o un idioma que falta devuelve el español, nunca una clave a medias.
 */
final class Messages
{
    public const DEFAULT_LOCALE = 'spa';

    /** @var array<string,array<string,string>> */
    private static array $tables = [];
    /** @var array<string,array<int,string>> */
    private static array $locales = [];

    /** @param array<int,string|int|float> $args argumentos de sprintf */
    public static function get(string $key, string $locale, array $args = [], ?string $dir = null): string
    {
        $text = self::find($key, $locale, $dir) ?? $key;

        return $args === [] ? $text : vsprintf($text, $args);
    }

    /** El texto de la clave en ese idioma, o en español; null si ninguno la tiene. */
    public static function find(string $key, string $locale, ?string $dir = null): ?string
    {
        return self::table($locale, $dir)[$key] ?? self::table(self::DEFAULT_LOCALE, $dir)[$key] ?? null;
    }

    /**
     * El idioma de salida para una cabecera Accept-Language («pt-BR,pt;q=0.9,en;q=0.8»): el de mayor peso `q`
     * (a igualdad, el primero) que tenga mensajes; español si ninguno. Las etiquetas de dos letras se traducen
     * con `LanguageRegistry::fromHeaderTag()`: es lo único que las acepta.
     */
    public static function negotiate(string $header, LanguageRegistry $registry, ?string $dir = null): string
    {
        $tags = [];
        foreach (explode(',', substr($header, 0, 200)) as $i => $part) {
            [$tag, $params] = array_pad(explode(';', $part, 2), 2, '');
            $q = preg_match('/q\s*=\s*([0-9.]+)/i', $params, $m) === 1 ? (float) $m[1] : 1.0;
            $tags[] = [$q, $i, explode('-', trim($tag))[0]];
        }
        usort($tags, static fn(array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        foreach ($tags as [$q, , $tag]) {
            $code = $q > 0 ? $registry->fromHeaderTag($tag) : null;
            if ($code !== null && in_array($code, self::locales($dir), true)) {
                return $code;
            }
        }

        return self::DEFAULT_LOCALE;
    }

    /** @return array<int,string> idiomas con mensajes propios, ordenados */
    public static function locales(?string $dir = null): array
    {
        $dir = self::dir($dir);
        if (!isset(self::$locales[$dir])) {
            $codes = array_map(static fn(string $f): string => basename($f, '.php'), glob($dir . '/*.php') ?: []);
            sort($codes);
            self::$locales[$dir] = $codes;
        }

        return self::$locales[$dir];
    }

    /** @return array<string,string> */
    public static function table(string $locale, ?string $dir = null): array
    {
        $locale = strtolower($locale);
        $dir = self::dir($dir);
        $file = $dir . '/' . $locale . '.php';

        return self::$tables[$file] ??= preg_match('/^[a-z]{3}$/', $locale) === 1 && is_file($file) ? require $file : [];
    }

    /**
     * Las claves que debe tener cada idioma: las de `spa` y el nombre y la descripción de cada categoría de
     * `risk-categories.php` (el español de las categorías vive ahí, no en `spa.php`).
     *
     * @return array<int,string>
     */
    public static function requiredKeys(string $riskCategoriesFile, ?string $dir = null): array
    {
        $keys = array_keys(self::table(self::DEFAULT_LOCALE, $dir));
        foreach (array_keys(require $riskCategoriesFile) as $id) {
            array_push($keys, "category.$id.name", "category.$id.description");
        }

        return $keys;
    }

    private static function dir(?string $dir): string
    {
        return rtrim($dir ?? dirname(__DIR__, 2) . '/config/messages', '/');
    }
}
