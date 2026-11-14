<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The texts the site says in the language of the subdomain (spa., eng.): one table per language in lang/{código}.php,
 * a PHP file returning clave => texto, with {marcas} for what is filled in. Spanish is the source: a language that lacks
 * a text (or the whole table) says it in Spanish, and a key nobody wrote is shown as itself, so a typo is seen, not
 * silent. Only the texts that go through here are translated; the rest of the views are still Spanish (docs/URL_STRUCTURES.md).
 *
 * A text is plain: the view or the code that shows it escapes it (esc()), never this.
 */
final class Textos
{
    /** @var array<string, array<string, string>> */
    private static array $tablas = [];

    /**
     * @param array<string, string|int> $datos what goes in each {marca}
     * @param string|null $idioma a language code; by default the one of this request
     */
    public static function t(string $clave, array $datos = [], ?string $idioma = null): string
    {
        $idioma ??= self::idioma();
        $texto = self::tabla($idioma)[$clave] ?? self::tabla(HostParser::IDIOMA_BASE)[$clave] ?? $clave;

        return $datos === [] ? $texto : strtr($texto, array_combine(
            array_map(static fn(string $marca): string => '{' . $marca . '}', array_keys($datos)),
            array_map('strval', array_values($datos))
        ));
    }

    /**
     * The keys a language has written (the tests compare each table with Spanish).
     *
     * @return list<string>
     */
    public static function claves(string $idioma): array
    {
        return array_keys(self::tabla($idioma));
    }

    /** The language of this request; Spanish outside public/index.php (a CLI script, a unit test). */
    private static function idioma(): string
    {
        try {
            return ServiceLocator::getInstance()->getLocale();
        } catch (\Throwable) {
            return HostParser::IDIOMA_BASE;
        }
    }

    /** @return array<string, string> */
    private static function tabla(string $idioma): array
    {
        if (!isset(self::$tablas[$idioma])) {
            // $idioma comes from a list of known codes, but a path is built from it: only three letters get through.
            $archivo = dirname(__DIR__) . '/lang/' . $idioma . '.php';
            self::$tablas[$idioma] = preg_match('/^[a-z]{3}$/', $idioma) === 1 && is_file($archivo) ? (array)require $archivo : [];
        }

        return self::$tablas[$idioma];
    }
}
