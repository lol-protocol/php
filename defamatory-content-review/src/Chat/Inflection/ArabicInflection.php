<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Plurales del árabe: el roto de las palabras de ArabicPlurals y, para el resto, el sano
 * (-ون / -ين en masculino, -ات en las que acaban en «ة»). Los femeninos ya van escritos en el
 * diccionario. Los verbos quedan literales. El artículo y las preposiciones pegadas los
 * resuelve CliticPrefixes. Ver TopicInflection.
 */
final class ArabicInflection implements TopicInflection
{
    public function forms(string $lemma, string $kind): array
    {
        if ($kind === 'verb') {
            return [$lemma];
        }

        return [$lemma, ...match (true) {
            isset(ArabicPlurals::BY_LEMMA[$lemma]) => ArabicPlurals::BY_LEMMA[$lemma],
            str_ends_with($lemma, 'ة') => [mb_substr($lemma, 0, -1) . 'ات'],
            (bool) preg_match('/[اىءي]$/u', $lemma) => [],
            default => [$lemma . 'ون', $lemma . 'ين'],
        }];
    }
}
