<?php

namespace DefamatoryContentReview\Chat;

use DefamatoryContentReview\Normalization\AccentFolding;

/** Las palabras de una línea y las marcas cerca de un término. Colaborador interno de TargetContext. */
final class TargetWindow
{
    private const WINDOW = 3;

    /**
     * ¿Hay una marca de `before` en las WINDOW palabras antes de alguna aparición
     * de `$found`, o de `after` en las WINDOW después? null si no aparece.
     *
     * @param list<string> $tokens
     * @param list<string> $found
     * @param list<string> $before
     * @param list<string> $after
     */
    public static function marked(array $tokens, array $found, array $before, array $after): ?bool
    {
        $positions = self::positions($tokens, $found);
        foreach ($positions as $start) {
            $left = ' ' . implode(' ', array_slice($tokens, max(0, $start - self::WINDOW), min($start, self::WINDOW))) . ' ';
            $right = ' ' . implode(' ', array_slice($tokens, $start + count($found), self::WINDOW)) . ' ';
            if (self::contains($left, $before) || self::contains($right, $after)) {
                return true;
            }
        }

        return $positions === [] ? null : false;
    }

    /** @return list<string> palabras en minúsculas y sin los diacríticos que el idioma pliega */
    public static function tokens(string $text, string $language): array
    {
        preg_match_all('/[\p{L}\p{M}\p{N}]+/u', AccentFolding::fold(mb_strtolower($text), $language), $words);

        return $words[0];
    }

    /**
     * @param list<string> $markers
     * @return list<string>
     */
    public static function phrases(array $markers, string $language): array
    {
        return array_values(array_filter(array_map(fn(string $m): string => implode(' ', self::tokens($m, $language)), $markers)));
    }

    /** @param list<string> $phrases */
    private static function contains(string $window, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($window, ' ' . $phrase . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $found
     * @return list<int>
     */
    private static function positions(array $tokens, array $found): array
    {
        $positions = [];
        for ($i = 0, $n = count($found); $n > 0 && $i + $n <= count($tokens); $i++) {
            if (array_slice($tokens, $i, $n) === $found) {
                $positions[] = $i;
            }
        }

        return $positions;
    }
}
