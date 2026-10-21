<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Plural del turco: -lar tras una vocal posterior (a, ı, o, u), -ler tras una
 * anterior (e, i, ö, ü): «salak» → «salaklar», «piç» → «piçler», «sahtekâr» → «sahtekârlar». Sustantivos y
 * adjetivos igual (en turco no hay género). Los verbos, que se aglutinan, quedan literales.
 */
final class TurkishInflection implements TopicInflection
{
    public function forms(string $lemma, string $kind): array
    {
        if ($kind === 'verb' || !preg_match('/([aıouâûAIOUÂÛeiöüîEİÖÜÎ])[^aıouâûAIOUÂÛeiöüîEİÖÜÎ]*$/u', $lemma, $vowel)) {
            return [$lemma];
        }

        return [$lemma, $lemma . (str_contains('aıouâûAIOUÂÛ', $vowel[1]) ? 'lar' : 'ler')];
    }
}
