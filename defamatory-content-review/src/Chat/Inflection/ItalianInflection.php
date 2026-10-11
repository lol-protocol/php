<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del italiano: verbos (ver ItalianVerbs), sustantivos
 * (número) y adjetivos (número y género). Las palabras que terminan en
 * consonante o en vocal acentuada no cambian en plural.
 */
final class ItalianInflection implements TopicInflection
{
    public function forms(string $lemma, string $kind): array
    {
        return match ($kind) {
            'verb' => ItalianVerbs::forms($lemma),
            'adj' => $this->plurals(str_ends_with($lemma, 'o') ? [$lemma, substr($lemma, 0, -1) . 'a'] : [$lemma]),
            default => $this->plurals([$lemma]),
        };
    }

    /**
     * pompino → pompini, tetta → tette, terrorista → terroristi/terroriste,
     * massacro → massacri, bacio → baci, amica → amiche, porco → porci/porchi.
     *
     * @param array<int,string> $singulars
     * @return array<int,string>
     */
    private function plurals(array $singulars): array
    {
        $forms = [];
        foreach ($singulars as $word) {
            $stem = substr($word, 0, -1);
            array_push($forms, $word, ...match (true) {
                (bool) preg_match('/[cg]o$/', $word) => [$stem . 'hi', $stem . 'i'],
                (bool) preg_match('/[cg]a$/', $word) => [$stem . 'he'],
                (bool) preg_match('/[cg]ia$/', $word) => [substr($word, 0, -2) . 'e', $stem . 'e'],
                str_ends_with($word, 'io') => [$stem],
                str_ends_with($word, 'ista') => [$stem . 'e', substr($word, 0, -1) . 'i'],
                str_ends_with($word, 'o'), str_ends_with($word, 'e') => [$stem . 'i'],
                str_ends_with($word, 'a') => [$stem . 'e'],
                default => [],
            });
        }

        return array_values(array_unique($forms));
    }
}
