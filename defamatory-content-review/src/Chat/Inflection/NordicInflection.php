<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Base del sueco, el danés y el noruego. Sustantivo: los plurales posibles de la
 * palabra (la clase de declinación no se sabe por la forma: «idiot» → «idioter»,
 * «skurk» → «skurkar»), así que se generan todos los de su terminación, y alguno
 * será inventado; llegan como mucho a revisión, y FalsePositives los mide.
 * Adjetivo: el plural/definido (-a o -e) y el neutro (-t), con y sin consonante doble
 * («dum» → «dumma», «dumt»). Cada idioma da sus terminaciones. Colaborador de TopicInflector.
 */
abstract class NordicInflection implements TopicInflection
{
    /** @return array<int,string> */
    abstract protected function plurals(string $stem, string $word): array;

    abstract protected function adjectiveEnding(): string;

    /** @return array<int,string> */
    abstract protected function verb(string $word): array;

    public function forms(string $lemma, string $kind): array
    {
        $forms = match ($kind) {
            'verb' => $this->verb($lemma),
            'adj' => $this->adjective($lemma),
            default => [$lemma, ...$this->plurals(preg_replace('/e$/u', '', $lemma) ?? $lemma, $lemma)],
        };

        return array_values(array_unique($forms));
    }

    /** @return array<int,string> */
    private function adjective(string $word): array
    {
        $doubled = preg_match('/[aeiouyåäøö][bdfgklmnprs]$/u', $word) === 1 ? [$word . mb_substr($word, -1) . $this->adjectiveEnding()] : [];

        return [$word, $word . $this->adjectiveEnding(), ...$doubled, $word . 't'];
    }
}
