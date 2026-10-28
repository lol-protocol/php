<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del húngaro con armonía vocálica: plural (-k), acusativo (-t),
 * plural acusativo y dativo («barom» → «baromok», «baromot», «baromokat», «baromnak»),
 * según la última vocal. Los adjetivos, cuando se usan como sustantivos, igual. Ver SuffixInflection.
 */
final class HungarianInflection extends SuffixInflection
{
    private const NOT_VOWEL = '[^aáeéiíoóöőuúüű]*';

    protected function nouns(): array
    {
        $tail = self::NOT_VOWEL;

        return [
            '/^(.+)a$/u' => ['ák', 'át', 'ákat', 'ának'],
            '/^(.+)e$/u' => ['ék', 'ét', 'éket', 'ének'],
            '/^(.+[oóuú])$/u' => ['k', 't', 'kat', 'nak'],
            '/^(.+[öőüű])$/u' => ['k', 't', 'ket', 'nek'],
            '/^(.+[ií])$/u' => ['k', 't', 'ket', 'nek'],
            "/^(.*[aá]{$tail})\$/u" => ['ak', 'at', 'akat', 'nak'],
            "/^(.*[oóuú]{$tail})\$/u" => ['ok', 'ot', 'okat', 'nak'],
            "/^(.*[öőüű]{$tail})\$/u" => ['ök', 'öt', 'öket', 'nek'],
            "/^(.*[eéií]{$tail})\$/u" => ['ek', 'et', 'eket', 'nek'],
        ];
    }

    protected function adjectives(): array
    {
        return $this->nouns();
    }
}
