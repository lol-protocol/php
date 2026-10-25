<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del rumano: plural con palatalización («idiot» → «idioți», «prost» → «proști»),
 * artículo pospuesto («idiotul», «curva») y el adjetivo por género y número. Ver SuffixInflection.
 */
final class RomanianInflection extends SuffixInflection
{
    protected function nouns(): array
    {
        return [
            '/^(.+)st$/u' => ['ști', 'stul', 'știi', 'știlor', 'sturi'],
            '/^(.+)t$/u' => ['ți', 'tul', 'ții', 'ților', 'turi'],
            '/^(.+)d$/u' => ['zi', 'dul', 'zii', 'zilor', 'duri'],
            '/^(.+)s$/u' => ['și', 'sul', 'șii', 'șilor', 'suri'],
            '/^(.+)z$/u' => ['ji', 'zul', 'jii', 'jilor', 'zuri'],
            '/^(.+)c$/u' => ['ci', 'cul', 'cii', 'cilor', 'curi'],
            '/^(.+)g$/u' => ['gi', 'gul', 'gii', 'gilor', 'guri'],
            '/^(.+[nlrmbpfv])$/u' => ['i', 'ul', 'ii', 'ilor', 'uri'],
            '/^(.+)ă$/u' => ['ă', 'e', 'i', 'a', 'ele', 'ei', 'elor'],
            '/^(.+)e$/u' => ['e', 'i', 'a', 'ile', 'ei', 'ilor'],
            '/^(.+)a$/u' => ['a', 'ale', 'ele', 'ei'],
        ];
    }

    protected function adjectives(): array
    {
        return [
            '/^(.+)st$/u' => ['stă', 'ste', 'ști'],
            '/^(.+)t$/u' => ['tă', 'te', 'ți', 'tul'],
            '/^(.+)d$/u' => ['dă', 'de', 'zi'],
            '/^(.+)s$/u' => ['să', 'se', 'și'],
            '/^(.+)c$/u' => ['că', 'ce', 'ci'],
            '/^(.+[bglmnprvfz])$/u' => ['ă', 'e', 'i'],
        ];
    }
}
