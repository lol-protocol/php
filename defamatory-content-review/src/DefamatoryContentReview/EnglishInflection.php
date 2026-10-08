<?php

namespace DefamatoryContentReview;

/**
 * Formas regulares del inglés: plural de sustantivos y -s/-ed/-ing de verbos.
 * No duplica consonantes («stopped»): lo irregular va a mano en `also`.
 */
final class EnglishInflection implements TopicInflection
{
    public function forms(string $lemma, string $kind): array
    {
        return match ($kind) {
            'verb' => $this->verb($lemma),
            'adj' => [$lemma],
            default => [$lemma, $this->plural($lemma)],
        };
    }

    private function plural(string $word): string
    {
        return match (true) {
            (bool) preg_match('/[^aeiou]y$/i', $word) => substr($word, 0, -1) . 'ies',
            (bool) preg_match('/(s|x|z|ch|sh)$/i', $word) => $word . 'es',
            default => $word . 's',
        };
    }

    /** @return array<int,string> */
    private function verb(string $word): array
    {
        if (preg_match('/[^aeiou]y$/i', $word)) {
            return [$word, $this->plural($word), substr($word, 0, -1) . 'ied', $word . 'ing'];
        }
        if (str_ends_with($word, 'e') && !str_ends_with($word, 'ee')) {
            return [$word, $word . 's', $word . 'd', substr($word, 0, -1) . 'ing'];
        }

        return [$word, $this->plural($word), $word . 'ed', $word . 'ing'];
    }
}
