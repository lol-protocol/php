<?php

namespace DefamatoryContentReview\Report;

use DefamatoryContentReview\Language\LanguageRegistry;

/**
 * Frases legibles del informe (explicaciones, recomendaciones, categorías, errores del endpoint) en el idioma
 * de salida. Cada idioma es un `<carpeta>/<iso639-3>.php` con claves planas (por omisión `config/messages/`);
 * `spa` es el origen y el respaldo: una clave o un idioma que falta devuelve el español, nunca una clave a medias.
 * Las categorías de riesgo en español (`category.<id>.name|description`) no se repiten en `spa.php`: salen de
 * `risk-categories.php`, la carpeta hermana de la de mensajes.
 */
final class Messages
{
    public const DEFAULT_LOCALE = 'spa';
    /** Claves que un idioma puede omitir: valen las de `spa` (el punto final solo cambia en japonés, hindi…). */
    public const OPTIONAL_KEYS = ['explanation.end'];

    /** @var array<string,array<string,string>> */
    private static array $tables = [];
    /** @var array<string,array<int,string>> */
    private static array $locales = [];

    /**
     * El texto de la clave con sus argumentos de sprintf. Si la plantilla del idioma no casa con los argumentos
     * (un archivo propio con un marcador de más o de menos), se usa la española; y si tampoco, la clave:
     * un mensaje roto no debe tumbar la respuesta.
     *
     * @param array<int,string|int|float> $args
     */
    public static function get(string $key, string $locale, array $args = [], ?string $dir = null): string
    {
        $templates = [self::table($locale, $dir)[$key] ?? null, self::table(self::DEFAULT_LOCALE, $dir)[$key] ?? null];
        foreach (array_unique(array_filter($templates, 'is_string')) as $text) {
            try {
                return $args === [] ? $text : vsprintf($text, $args);
            } catch (\ValueError | \ArgumentCountError) {
                continue;
            }
        }

        return $key;
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

        if (!isset(self::$tables[$file])) {
            $table = preg_match('/^[a-z]{3}$/', $locale) === 1 && is_file($file) ? require $file : [];
            self::$tables[$file] = $locale === self::DEFAULT_LOCALE ? $table + self::spanishCategories(dirname($dir) . '/risk-categories.php') : $table;
        }

        return self::$tables[$file];
    }

    /** @return array<string,string> */
    private static function spanishCategories(string $file): array
    {
        $texts = [];
        foreach (is_file($file) ? require $file : [] as $id => $category) {
            $texts["category.$id.name"] = $category['name'];
            $texts["category.$id.description"] = $category['description'];
        }

        return $texts;
    }

    /**
     * Las claves que debe tener cada idioma: las de `spa` (las categorías incluidas) salvo `OPTIONAL_KEYS`.
     *
     * @return array<int,string>
     */
    public static function requiredKeys(?string $dir = null): array
    {
        return array_values(array_diff(array_keys(self::table(self::DEFAULT_LOCALE, $dir)), self::OPTIONAL_KEYS));
    }

    private static function dir(?string $dir): string
    {
        return rtrim($dir ?? dirname(__DIR__, 2) . '/config/messages', '/');
    }
}
