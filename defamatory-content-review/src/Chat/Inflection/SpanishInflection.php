<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del español: verbos (ver SpanishVerbs), sustantivos
 * (número) y adjetivos (número y género). Sólo cubre lo regular: lo
 * irregular («degüello») va a mano en el campo `also` de la entrada.
 * Escribe el adjetivo en masculino y el sustantivo en singular.
 */
final class SpanishInflection implements TopicInflection
{
    public function forms(string $lemma, string $kind): array
    {
        return match ($kind) {
            'verb' => SpanishVerbs::forms($lemma),
            'adj' => $this->plurals($this->genders($lemma)),
            default => $this->plurals([$lemma]),
        };
    }

    /** @return array<int,string> */
    private function genders(string $word): array
    {
        return match (true) {
            str_ends_with($word, 'o') => [$word, substr($word, 0, -1) . 'a'],
            str_ends_with($word, 'a'), str_ends_with($word, 'e') => [$word],
            default => [$word, $word . 'a'],
        };
    }

    /**
     * @param array<int,string> $singulars
     * @return array<int,string>
     */
    private function plurals(array $singulars): array
    {
        $forms = [];
        foreach ($singulars as $word) {
            $forms[] = $word;
            $forms[] = match (true) {
                str_ends_with($word, 'z') => substr($word, 0, -1) . 'ces',
                (bool) preg_match('/[aeiouáéíóú]$/iu', $word) => $word . 's',
                default => $word . 'es',
            };
        }

        return array_values(array_unique($forms));
    }
}
