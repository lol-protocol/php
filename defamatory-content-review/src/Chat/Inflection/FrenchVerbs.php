<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Conjugación regular del francés para FrenchInflection: los grupos -er, -ir
 * (-iss-: «finissons») y -re, con tiempos simples, participio y el
 * imperativo con pronombre («baise-moi», «tue-le»: el guion se quita al
 * buscar). Los pronombres sueltos («je vais te tuer») ya son otra palabra.
 */
final class FrenchVerbs extends RomanceVerbs
{
    protected const ENDINGS = [
        'er' => [
            'e', 'es', 'ent', 'ons', 'ez', 'ais', 'ait', 'ions', 'iez', 'aient', 'ai', 'as', 'a', 'ames', 'ates',
            'erent', 'erai', 'eras', 'era', 'erons', 'erez', 'eront', 'erais', 'erait', 'erions', 'eriez',
            'eraient', 'asse', 'asses', 'at', 'assions', 'assiez', 'assent', 'ant', 'ee', 'ees', 'er',
        ],
        'ir' => [
            'is', 'it', 'issons', 'issez', 'issent', 'issais', 'issait', 'issions', 'issiez', 'issaient', 'imes',
            'ites', 'irent', 'irai', 'iras', 'ira', 'irons', 'irez', 'iront', 'irais', 'irait', 'irions',
            'iriez', 'iraient', 'isse', 'isses', 'i', 'ie', 'ies', 'issant', 'ir',
        ],
        're' => [
            's', 'ons', 'ez', 'ent', 'ais', 'ait', 'ions', 'iez', 'aient', 'is', 'it', 'imes', 'ites', 'irent',
            'rai', 'ras', 'ra', 'rons', 'rez', 'ront', 'rais', 'rait', 'rions', 'riez', 'raient', 'e', 'es',
            'u', 'ue', 'us', 'ues', 'ant', 're',
        ],
    ];
    protected const HOSTS = ['er' => ['e', 'ez', 'ons'], 'ir' => ['is', 'issez'], 're' => ['s', 'ez']];
    protected const CLITICS = ['moi', 'toi', 'le', 'la', 'les', 'lui', 'nous', 'vous', 'leur'];

    /** manger→mangeons (la ç de lancer se pliega a c). */
    protected static function spell(string $stem, string $ending, string $class): string
    {
        return $class === 'er' && str_ends_with($stem, 'g') && ($ending[0] === 'a' || $ending[0] === 'o') ? $stem . 'e' : $stem;
    }
}
