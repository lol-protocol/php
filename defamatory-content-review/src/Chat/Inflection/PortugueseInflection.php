<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del portugués: verbos (ver PortugueseVerbs), sustantivos
 * (número) y adjetivos (número y género). Escribe el adjetivo en masculino,
 * el sustantivo en singular y sin tildes («invasao»).
 */
final class PortugueseInflection implements TopicInflection
{
    public function forms(string $lemma, string $kind): array
    {
        return match ($kind) {
            'verb' => PortugueseVerbs::forms($lemma),
            'adj' => $this->plurals($this->genders($lemma)),
            default => $this->plurals([$lemma]),
        };
    }

    /** @return array<int,string> */
    private function genders(string $word): array
    {
        return match (true) {
            str_ends_with($word, 'o') => [$word, substr($word, 0, -1) . 'a'],
            str_ends_with($word, 'or'), str_ends_with($word, 'es') => [$word, $word . 'a'],
            default => [$word],
        };
    }

    /**
     * Invasão → invasões (y las otras dos del plural en -ão), míssil → mísseis,
     * homem → homens, terror → terrores. Sin tilde: ninguna hace falta.
     *
     * @param array<int,string> $singulars
     * @return array<int,string>
     */
    private function plurals(array $singulars): array
    {
        $forms = [];
        foreach ($singulars as $word) {
            $stem = substr($word, 0, -2);
            array_push($forms, $word, ...match (true) {
                str_ends_with($word, 'ao') => [$stem . 'oes', $stem . 'aos', $stem . 'aes'],
                str_ends_with($word, 'm') => [substr($word, 0, -1) . 'ns'],
                str_ends_with($word, 'il') => [$stem . 'is', $stem . 'eis'],
                str_ends_with($word, 'el') => [$stem . 'eis'],
                (bool) preg_match('/[aou]l$/', $word) => [substr($word, 0, -1) . 'is'],
                (bool) preg_match('/[rzs]$/', $word) => [$word . 'es'],
                default => [$word . 's'],
            });
        }

        return array_values(array_unique($forms));
    }
}
