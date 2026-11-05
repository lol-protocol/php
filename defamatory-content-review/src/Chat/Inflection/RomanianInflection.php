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
            '/^(.+)st$/u' => ['ști', 'stul', 'stului', 'stule', 'știi', 'știlor', 'sturi'],
            '/^(.+)t$/u' => ['ți', 'tul', 'tului', 'tule', 'ții', 'ților', 'turi', 'turilor'],
            '/^(.+)d$/u' => ['zi', 'dul', 'dului', 'dule', 'zii', 'zilor', 'duri'],
            '/^(.+)s$/u' => ['și', 'sul', 'sului', 'sule', 'șii', 'șilor', 'suri', 'surilor'],
            '/^(.+)z$/u' => ['ji', 'zul', 'zului', 'zule', 'jii', 'jilor', 'zuri'],
            '/^(.+)c$/u' => ['ci', 'cul', 'cului', 'cule', 'cii', 'cilor', 'curi', 'curilor'],
            '/^(.+)g$/u' => ['gi', 'gul', 'gului', 'gule', 'gii', 'gilor', 'guri'],
            '/^(.+[nlrmbpfv])$/u' => ['i', 'ul', 'ului', 'ule', 'ii', 'ilor', 'uri'],
            '/^(.+)ă$/u' => ['a', 'ei', 'elor', 'ele', 'e', 'i'], // «târfă» → «târfe»: el plural indefinido casi no está en UniMorph
            '/^(.+)e$/u' => ['ea', 'a', 'ilor', 'ile', 'ii', 'i', 'ei'],
            '/^(.+)a$/u' => ['a', 'ale', 'ele', 'ei'],
            '/^(.+)u$/u' => ['ul', 'ului', 'ule', 'ilor', 'ii', 'elor', 'ele'],
        ];
    }

    protected function adjectives(): array
    {
        return [
            '/^(.+)st$/u' => ['stă', 'ste', 'ști', 'stul', 'stului', 'știi', 'știlor', 'stele', 'stelor', 'stei', 'sta'],
            '/^(.+)t$/u' => ['tă', 'te', 'ți', 'tul', 'tului', 'ții', 'ților', 'tele', 'telor', 'tei', 'ta'],
            '/^(.+)d$/u' => ['dă', 'de', 'zi', 'dul', 'dului', 'zii', 'zilor', 'dele', 'delor', 'dei', 'da'],
            '/^(.+)s$/u' => ['să', 'se', 'și', 'sul', 'sului', 'șii', 'șilor'],
            '/^(.+)c$/u' => ['că', 'ce', 'ci', 'cul', 'cului', 'cii', 'cilor', 'cele', 'celor', 'cei', 'ca'],
            '/^(.+)u$/u' => ['ul', 'ului', 'ii', 'a', 'ei', 'e', 'i', 'ilor', 'elor', 'ele', 'ă'],
            '/^(.+[bglmnprvfz])$/u' => ['ă', 'e', 'i', 'ul', 'ului', 'ii', 'ilor', 'elor', 'ele', 'ei', 'a'],
        ];
    }
}
