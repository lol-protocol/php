<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del francés: verbos (ver FrenchVerbs), sustantivos
 * (número) y adjetivos (número y género). Escribe el adjetivo en masculino y
 * el sustantivo en singular.
 */
final class FrenchInflection implements TopicInflection
{
    private const FEMININE = ['eux' => 'euse', 'if' => 'ive', 'er' => 'ere', 'en' => 'enne', 'on' => 'onne', 'el' => 'elle', 'et' => 'ette'];

    public function forms(string $lemma, string $kind): array
    {
        return match ($kind) {
            'verb' => FrenchVerbs::forms($lemma),
            'adj' => $this->plurals([$lemma, $this->feminine($lemma)]),
            default => $this->plurals([$lemma]),
        };
    }

    private function feminine(string $word): string
    {
        if (str_ends_with($word, 'e')) {
            return $word;
        }
        foreach (self::FEMININE as $masculine => $feminine) {
            if (str_ends_with($word, $masculine)) {
                return substr($word, 0, -strlen($masculine)) . $feminine;
            }
        }

        return $word . 'e';
    }

    /**
     * viol → viols, missile → missiles, cheval → chevaux, feu → feux; las que
     * terminan en s, x o z no cambian.
     *
     * @param array<int,string> $singulars
     * @return array<int,string>
     */
    private function plurals(array $singulars): array
    {
        $forms = [];
        foreach ($singulars as $word) {
            $forms[] = $word;
            $forms[] = match (true) {
                (bool) preg_match('/[sxz]$/', $word) => $word,
                str_ends_with($word, 'al') => substr($word, 0, -2) . 'aux',
                (bool) preg_match('/(eau|au|eu)$/', $word) => $word . 'x',
                default => $word . 's',
            };
        }

        return array_values(array_unique($forms));
    }
}
