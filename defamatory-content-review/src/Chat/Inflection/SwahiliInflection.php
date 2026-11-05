<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Plural del suajili por el prefijo de la clase nominal: personas «m-» → «wa-» («mjinga» → «wajinga»,
 * «mwizi» → «wezi», «mwongo» → «waongo», «mwembamba» → «wembamba»), «ki-» → «vi-» («kibaka» → «vibaka») y «ji-» → «ma-ji-» («jitu» →
 * «majitu»). Las demás clases (n-, ma-, u-) y los adjetivos y verbos quedan literales. Sin medir: UniMorph
 * no trae sustantivos suajili ni hay lista de frecuencias para los falsos positivos; solo se aplican
 * las reglas más seguras, y las palabras con «mb-» de la clase n- van en NOT_PERSONS. Ver TopicInflection.
 */
final class SwahiliInflection implements TopicInflection
{
    /** Empiezan por «m» pero son de la clase n- (no cambian en plural). */
    private const NOT_PERSONS = ['mbwa', 'mbogo', 'mbaya', 'mboo', 'matako', 'mavi'];

    public function forms(string $lemma, string $kind): array
    {
        if ($kind !== 'noun' || in_array($lemma, self::NOT_PERSONS, true) || mb_strlen($lemma) < 4) {
            return [$lemma];
        }

        return array_values(array_unique([$lemma, ...match (true) {
            str_starts_with($lemma, 'mwi') => ['we' . substr($lemma, 3)],
            str_starts_with($lemma, 'mwa'), str_starts_with($lemma, 'mwe') => [substr($lemma, 1)],
            str_starts_with($lemma, 'mw') => ['wa' . substr($lemma, 2)],
            str_starts_with($lemma, 'mu') => ['wa' . substr($lemma, 2)],
            (bool) preg_match('/^m[^aeiou]/u', $lemma) => ['wa' . substr($lemma, 1)],
            str_starts_with($lemma, 'ki') => ['vi' . substr($lemma, 2)],
            str_starts_with($lemma, 'ji') => ['ma' . $lemma],
            default => [],
        }]));
    }
}
