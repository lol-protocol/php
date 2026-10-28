<?php

namespace DefamatoryContentReview\Chat;

/**
 * Preposiciones y artículo pegados a la palabra: en hebreo «הטיפש» (el tonto), «ובטיפש»;
 * en árabe «الغبي» (el tonto), «بالغبي». Si la palabra no está tal cual, se prueba sin
 * esas letras iniciales (hasta tres) y con un resto de MIN_REST letras o más; lo hallado así
 * trae `prefix => clitic` y nunca bloquea solo: «מחזיר» (devuelve) empieza con «מ» + «חזיר»
 * (cerdo). Solo en los idiomas de PREFIXES. Colaborador de SearchStrategy.
 */
final class CliticPrefixes
{
    private const PREFIXES = [
        'heb' => ['ו', 'ה', 'ב', 'כ'],
        'ara' => ['ال', 'و', 'ب', 'ل', 'ف', 'ك'],
    ];
    private const MIN_REST = 3;
    private const MAX_PREFIXES = 3;

    public static function applies(string $language): bool
    {
        return isset(self::PREFIXES[$language]);
    }

    /**
     * @param callable(string):?array<string,mixed> $search
     * @return callable(string):?array<string,mixed>
     */
    public static function searcher(callable $search, string $language): callable
    {
        $prefixes = self::PREFIXES[$language] ?? [];

        return function (string $phrase) use ($search, $prefixes): ?array {
            if (($entry = $search($phrase)) !== null || str_contains($phrase, ' ')) {
                return $entry;
            }

            return self::strip($phrase, $search, $prefixes, self::MAX_PREFIXES);
        };
    }

    /**
     * @param callable(string):?array<string,mixed> $search
     * @param list<string> $prefixes
     * @return array<string,mixed>|null
     */
    private static function strip(string $word, callable $search, array $prefixes, int $left): ?array
    {
        foreach ($prefixes as $prefix) {
            $rest = str_starts_with($word, $prefix) ? substr($word, strlen($prefix)) : null;
            if ($left < 1 || $rest === null || mb_strlen($rest) < self::MIN_REST) {
                continue;
            }
            $entry = $search($rest) ?? self::strip($rest, $search, $prefixes, $left - 1);
            if ($entry !== null) {
                return $entry + ['prefix' => 'clitic'];
            }
        }

        return null;
    }
}
