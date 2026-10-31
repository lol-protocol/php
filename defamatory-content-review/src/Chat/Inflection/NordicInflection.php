<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Base del sueco, el danés y el noruego. Sustantivo: los plurales posibles de la
 * palabra (la clase de declinación no se sabe por la forma: «idiot» → «idioter»,
 * «skurk» → «skurkar»), así que se generan todos los de su terminación, y alguno
 * será inventado; llegan como mucho a revisión, y FalsePositives los mide.
 * Con el artículo pospuesto («idioten», «idioterna»; cada idioma lo suyo). Adjetivo: el plural/definido (-a o -e) y el neutro (-t), con y sin consonante doble
 * («dum» → «dumma», «dumt»). Cada idioma da sus terminaciones. Colaborador de TopicInflector.
 */
abstract class NordicInflection implements TopicInflection
{
    /** @return array<int,string> */
    abstract protected function plurals(string $stem, string $word): array;

    /**
     * Las formas con el artículo pospuesto: «idioten», «idioterna».
     *
     * @param list<string> $plurals
     * @return list<string>
     */
    abstract protected function definite(string $word, array $plurals): array;

    abstract protected function adjectiveEnding(): string;

    /** @return array<int,string> */
    abstract protected function verb(string $word): array;

    public function forms(string $lemma, string $kind): array
    {
        $forms = match ($kind) {
            'verb' => $this->verb($lemma),
            'adj' => $this->adjective($lemma),
            default => $this->nounForms($lemma),
        };

        return array_values(array_unique($forms));
    }

    /** @return array<int,string> */
    private function nounForms(string $lemma): array
    {
        if (mb_strlen($lemma) <= 2) { // «so» → «son», «ku» → «kun»: una letra más y es otra palabra
            return [$lemma];
        }
        $plurals = $this->plurals(preg_replace('/e$/u', '', $lemma) ?? $lemma, $lemma);

        return [$lemma, ...$plurals, ...$this->definite($lemma, $plurals)];
    }

    /** @return array<int,string> */
    private function adjective(string $word): array
    {
        $doubled = preg_match('/[aeiouyåäøö][bdfgklmnprs]$/u', $word) === 1 ? [$word . mb_substr($word, -1) . $this->adjectiveEnding()] : [];

        return [$word, $word . $this->adjectiveEnding(), ...$doubled, $word . 't'];
    }
}
