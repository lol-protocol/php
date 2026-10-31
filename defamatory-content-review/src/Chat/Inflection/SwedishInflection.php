<?php

namespace DefamatoryContentReview\Chat\Inflection;

/** Formas regulares del sueco: plural (-or, -ar, -er), adjetivo (-a, -t) y verbos en -a. Ver NordicInflection. */
final class SwedishInflection extends NordicInflection
{
    protected function plurals(string $stem, string $word): array
    {
        return match (true) {
            str_ends_with($word, 'are'), str_ends_with($word, 'ere') => [],
            str_ends_with($word, 'a') => [substr($word, 0, -1) . 'or'],
            str_ends_with($word, 'e') => [$stem . 'ar'],
            (bool) preg_match('/[^aeiouyåäö](el|en|er)$/u', $word) => [substr($word, 0, -2) . substr($word, -1) . 'ar', $word . 'ar', $word . 'er'],
            (bool) preg_match('/[aeiouyåäö]$/u', $word) => [$word . 'r', $word . 'ar'],
            default => [$word . 'ar', $word . 'er'],
        };
    }

    protected function definite(string $word, array $plurals): array
    {
        $vowel = preg_match('/[aeiouyåäö]$/u', $word) === 1;

        return [$word . ($vowel ? 'n' : 'en'), $word . ($vowel ? 't' : 'et'), ...array_map(fn(string $p): string => $p . (str_ends_with($p, 'r') ? 'na' : 'en'), $plurals)];
    }

    protected function adjectiveEnding(): string
    {
        return 'a';
    }

    protected function verb(string $word): array
    {
        $stem = str_ends_with($word, 'a') ? substr($word, 0, -1) : $word;

        return [$word, $stem, $stem . 'ar', $stem . 'ade', $stem . 'at', $stem . 'ande'];
    }
}
