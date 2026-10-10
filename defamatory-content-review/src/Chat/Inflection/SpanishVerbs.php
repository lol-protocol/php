<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Conjugación regular del español para SpanishInflection: todos los tiempos
 * simples, participio, gerundio y las formas con pronombre pegado («matarlos»,
 * «fóllame»). Las terminaciones van sin tilde porque WordList las pliega.
 * La mecánica es la de RomanceVerbs; aquí sólo las tablas y la ortografía.
 */
final class SpanishVerbs extends RomanceVerbs
{
    protected const ENDINGS = [
        'ar' => [
            'o', 'as', 'a', 'amos', 'ais', 'an', 'e', 'es', 'emos', 'eis', 'en', 'aste', 'asteis', 'aron',
            'aba', 'abas', 'abamos', 'abais', 'aban', 'are', 'aras', 'ara', 'aremos', 'areis', 'aran',
            'aria', 'arias', 'ariamos', 'ariais', 'arian', 'aramos', 'arais', 'ase', 'ases', 'asemos',
            'aseis', 'asen', 'ad', 'ando', 'ado', 'ada', 'ados', 'adas', 'ar',
        ],
        'er' => [
            'o', 'es', 'e', 'emos', 'eis', 'en', 'i', 'iste', 'io', 'imos', 'isteis', 'ieron', 'ia', 'ias',
            'iamos', 'iais', 'ian', 'ere', 'eras', 'era', 'eremos', 'ereis', 'eran', 'eria', 'erias',
            'eriamos', 'eriais', 'erian', 'a', 'as', 'amos', 'ais', 'an', 'iera', 'ieras', 'ieramos',
            'ierais', 'ieran', 'iese', 'ieses', 'iesemos', 'ieseis', 'iesen', 'ed', 'iendo', 'ido', 'ida',
            'idos', 'idas', 'er',
        ],
        'ir' => [
            'o', 'es', 'e', 'imos', 'is', 'en', 'i', 'iste', 'io', 'isteis', 'ieron', 'ia', 'ias', 'iamos',
            'iais', 'ian', 'ire', 'iras', 'ira', 'iremos', 'ireis', 'iran', 'iria', 'irias', 'iriamos',
            'iriais', 'irian', 'a', 'as', 'amos', 'ais', 'an', 'iera', 'ieras', 'ieramos', 'ierais',
            'ieran', 'iese', 'ieses', 'iesemos', 'ieseis', 'iesen', 'id', 'iendo', 'ido', 'ida', 'idos',
            'idas', 'ir',
        ],
    ];
    /** Terminaciones que admiten pronombre pegado: infinitivo, gerundio e imperativo. */
    protected const HOSTS = [
        'ar' => ['ar', 'ando', 'a', 'e', 'en'],
        'er' => ['er', 'iendo', 'e', 'a', 'an'],
        'ir' => ['ir', 'iendo', 'e', 'a', 'an'],
    ];
    protected const CLITICS = ['me', 'te', 'se', 'nos', 'lo', 'la', 'los', 'las', 'le', 'les'];

    /** Cambios de ortografía que mantienen el sonido: chingar→chingue, sacar→saque, coger→cojo. */
    protected static function spell(string $stem, string $ending, string $class): string
    {
        $last = substr($stem, -1);
        if ($class === 'ar' && $ending[0] === 'e') {
            return match ($last) {
                'g' => $stem . 'u',
                'c' => substr($stem, 0, -1) . 'qu',
                'z' => substr($stem, 0, -1) . 'c',
                default => $stem,
            };
        }

        return $class !== 'ar' && $last === 'g' && ($ending[0] === 'o' || $ending[0] === 'a')
            ? substr($stem, 0, -1) . 'j'
            : $stem;
    }
}
