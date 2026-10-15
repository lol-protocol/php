<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del neerlandés: el plural (-en, o -s tras -el, -em, -en,
 * -er, -je) y el adjetivo con -e, con la ortografía de las dos: vocal corta
 * dobla la consonante («kut» → «kutten», «dom» → «domme»), vocal larga doble
 * pierde una letra («aap» → «apen») y entonces f/s pasan a v/z («teef» →
 * «teven», «vies» no: «ie» no es doble → «vieze»). Los verbos quedan literales.
 */
final class DutchInflection implements TopicInflection
{
    public function forms(string $lemma, string $kind): array
    {
        return match ($kind) {
            'noun' => [$lemma, preg_match('/[^aeiou](el|em|en|er|je)$|[aiouy]$/u', $lemma) === 1 ? $lemma . (preg_match('/[aiouy]$/u', $lemma) ? "'s" : 's') : $this->withE($lemma) . 'n'],
            'adj' => [$lemma, $this->withE($lemma)],
            default => [$lemma],
        };
    }

    /** La palabra con -e y su ajuste ortográfico: «dom» → «domme», «aap» → «ape», «vies» → «vieze». */
    private function withE(string $word): string
    {
        if (str_ends_with($word, 'e')) {
            return $word;
        }
        if (preg_match('/^(.*?)(?<![aeou])(aa|ee|oo|uu)([^aeiou])$/u', $word, $m)) { // «idioot» → «idioten»
            return $m[1] . $m[2][0] . strtr($m[3], 'fs', 'vz') . 'e';
        }
        if (preg_match('/(ie|oe|ui|ei|ij|eu|ou|au)[fs]$/u', $word)) {
            return substr($word, 0, -1) . strtr(substr($word, -1), 'fs', 'vz') . 'e';
        }
        if (preg_match('/(^|[^aeiou])[aeiou]([bdfgklmnprst])$/u', $word, $m) && !preg_match('/(ig|lijk|isch|el|em|en|er)$/u', $word)) { // sílaba átona: «mager» → «magere»
            return $word . $m[2] . 'e';
        }

        return $word . 'e';
    }
}
