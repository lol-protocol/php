<?php

namespace DefamatoryContentReview\Chat\Inflection;

/** Formas regulares del danés: plural (-er, -e, -r), adjetivo (-e, -t) y verbos en -e. Ver NordicInflection. */
final class DanishInflection extends NordicInflection
{
    protected function plurals(string $stem, string $word): array
    {
        return match (true) {
            str_ends_with($word, 'e') => [$word . 'r'],
            str_ends_with($word, 'er') => [$word . 'e'],
            default => [$word . 'er', $word . 'e'],
        };
    }

    protected function definite(string $word, array $plurals): array
    {
        $vowel = preg_match('/[aeiouyæøå]$/u', $word) === 1;

        return [$word . ($vowel ? 'n' : 'en'), $word . ($vowel ? 't' : 'et'), ...array_map(fn(string $p): string => $p . 'ne', $plurals)];
    }

    protected function adjectiveEnding(): string
    {
        return 'e';
    }

    protected function verb(string $word): array
    {
        $stem = str_ends_with($word, 'e') ? substr($word, 0, -1) : $word;

        return [$word, $stem, $stem . 'er', $stem . 'ede', $stem . 'et', $stem . 'ende'];
    }
}
