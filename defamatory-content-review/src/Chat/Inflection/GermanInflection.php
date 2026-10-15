<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del alemán. Adjetivo: las cinco terminaciones («dumm» →
 * «dumme», «dummer», «dummen», «dummes», «dummem»). Sustantivo: sólo los plurales
 * que siguen una regla segura —«Hure» → «Huren», «Idiotin» → «Idiotinnen»,
 * «Idiot» → «Idioten», «Wichser» → «Wichsern» (dativo)—; los que cambian la vocal
 * («Arsch» → «Ärsche») van a mano en `also`. Los verbos quedan literales.
 */
final class GermanInflection implements TopicInflection
{
    /** Sustantivos débiles: el plural (y todo caso salvo el nominativo) en -en. */
    private const WEAK = '/(ist|ant|ent|ot|at|ath|graph|nom|soph)$/u';

    public function forms(string $lemma, string $kind): array
    {
        return match ($kind) {
            'adj' => $this->adjective($lemma),
            'noun' => array_values(array_unique([$lemma, ...$this->plural($lemma)])),
            default => [$lemma],
        };
    }

    /** @return array<int,string> */
    private function adjective(string $word): array
    {
        $stem = str_ends_with($word, 'e') ? substr($word, 0, -1) : $word;

        return array_values(array_unique([$word, ...array_map(fn(string $ending): string => $stem . $ending, ['e', 'er', 'en', 'es', 'em'])]));
    }

    /** @return array<int,string> */
    private function plural(string $word): array
    {
        return match (true) {
            str_ends_with($word, 'e') => [$word . 'n'],
            (bool) preg_match('/[^aeiouäöü]in$/u', $word) => [$word . 'nen'], // «Äffin»; «Schwein» no
            (bool) preg_match('/(er|el)$/u', $word) => [$word . 'n'],
            (bool) preg_match(self::WEAK, $word) => [$word . 'en'],
            default => [],
        };
    }
}
