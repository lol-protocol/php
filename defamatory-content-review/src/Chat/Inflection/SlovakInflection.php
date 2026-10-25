<?php

namespace DefamatoryContentReview\Chat\Inflection;

/** Declinación regular del eslovaco: sustantivos masculinos, femeninos y neutros, y adjetivos en -ý/-í. Ver SuffixInflection. */
final class SlovakInflection extends SuffixInflection
{
    protected function nouns(): array
    {
        return [
            '/^(.+)ec$/u' => ['ec', 'ca', 'covi', 'com', 'ci', 'cov', 'cami'], // zradca
            '/^(.+)ok$/u' => ['ok', 'ka', 'kovi', 'kom', 'ky', 'kov', 'kami'],
            '/^(.+[bdfghklmnprstvzčšžcjďťňľŕĺ])$/u' => ['a', 'u', 'om', 'e', 'ovi', 'i', 'y', 'ov', 'mi', 'och', 'om'],
            '/^(.+)a$/u' => ['y', 'e', 'u', 'ou', 'o', 'ám', 'ách', 'ami', ''],
            '/^(.+)o$/u' => ['a', 'u', 'om', 'e', 'ám', 'ách', 'ami'],
            '/^(.+)e$/u' => ['a', 'u', 'om', 'ia', 'í'],
        ];
    }

    protected function adjectives(): array
    {
        return [
            '/^(.+)ý$/u' => ['ý', 'ého', 'ému', 'ým', 'om', 'á', 'ej', 'ú', 'ou', 'é', 'í', 'ých', 'ými'],
            '/^(.+)í$/u' => ['í', 'ieho', 'iemu', 'ím', 'ia', 'ej', 'iu', 'ie', 'ích', 'ími'],
        ];
    }
}
