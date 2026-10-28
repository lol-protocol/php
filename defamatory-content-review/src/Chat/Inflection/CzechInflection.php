<?php

namespace DefamatoryContentReview\Chat\Inflection;

/** Declinación regular del checo: sustantivos masculinos, femeninos y neutros, y adjetivos en -ý/-í. Ver SuffixInflection. */
final class CzechInflection extends SuffixInflection
{
    protected function nouns(): array
    {
        return [
            '/^(.+)ek$/u' => ['ek', 'ka', 'kovi', 'ku', 'ke', 'kem', 'kové', 'ků', 'kům', 'cích', 'ky'], // blbeček → blbečka
            '/^(.+)ec$/u' => ['ec', 'ce', 'ci', 'covi', 'cem', 'cové', 'ců', 'cům', 'cích'], // zrádce
            '/^(.+[čšžřcjďťň])$/u' => ['e', 'i', 'ovi', 'em', 'ové', 'ů', 'ům', 'ích', 'ech'],
            '/^(.+[bdfghklmnprstvz])$/u' => ['a', 'ovi', 'u', 'e', 'em', 'i', 'y', 'ové', 'ů', 'ům', 'ech', 'ích'],
            '/^(.+)a$/u' => ['y', 'ovi', 'u', 'o', 'ou', 'ě', 'ové', 'ů', 'ům', 'ám', 'ách', 'ami', ''],
            '/^(.+)[eě]$/u' => ['e', 'ě', 'i', 'í', 'ím', 'ích', 'emi'],
            '/^(.+)o$/u' => ['a', 'u', 'em', 'ě', 'e', 'ům', 'ech', 'y'],
        ];
    }

    protected function adjectives(): array
    {
        return [
            '/^(.+)ý$/u' => ['ý', 'ého', 'ému', 'ým', 'ém', 'á', 'é', 'ou', 'í', 'ých', 'ými'],
            '/^(.+)í$/u' => ['í', 'ího', 'ímu', 'ím', 'ích', 'ími', 'e'],
        ];
    }
}
