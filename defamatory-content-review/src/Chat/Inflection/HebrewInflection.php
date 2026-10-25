<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del hebreo sin vocalización: plural masculino (-ים) y femenino (-ות),
 * y del adjetivo, el femenino (-ה, -ית) y los plurales. Las letras finales (ם ן ך ף ץ)
 * vuelven a su forma normal al añadir un sufijo. No genera las preposiciones pegadas
 * (ה־, ו־, ב־…). Ver SuffixInflection.
 */
final class HebrewInflection extends SuffixInflection
{
    protected function nouns(): array
    {
        return $this->rules(['ים', 'ות'], ['ות', 'ה']);
    }

    protected function adjectives(): array
    {
        return $this->rules(['ה', 'ים', 'ות', 'ית'], ['ות', 'ה']);
    }

    /**
     * @param list<string> $endings tras una letra final o cualquier otra consonante
     * @param list<string> $feminine tras -ה o -ת
     * @return array<string,list<string>>
     */
    private function rules(array $endings, array $feminine): array
    {
        $rules = ['/^(.+)ה$/u' => $feminine, '/^(.+)ת$/u' => ['ות', 'ים', 'ה']];
        foreach (['ם' => 'מ', 'ן' => 'נ', 'ך' => 'כ', 'ף' => 'פ', 'ץ' => 'צ'] as $final => $plain) {
            $rules["/^(.+){$final}\$/u"] = array_map(fn(string $e): string => $plain . $e, $endings);
        }

        return $rules + ['/^(.+)$/u' => $endings];
    }
}
