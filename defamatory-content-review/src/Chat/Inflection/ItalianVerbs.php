<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Conjugación regular del italiano para ItalianInflection: tiempos simples,
 * participio, gerundio y pronombre pegado al infinitivo sin -e, al gerundio y
 * al imperativo («ammazzarti», «uccidendolo», «ammazzala»). Los verbos en
 * -isc- («finisco») van a mano en `also`.
 */
final class ItalianVerbs extends RomanceVerbs
{
    protected const ENDINGS = [
        'are' => [
            'o', 'i', 'a', 'iamo', 'ate', 'ano', 'avo', 'avi', 'ava', 'avamo', 'avate', 'avano', 'ai', 'asti',
            'ammo', 'aste', 'arono', 'ero', 'erai', 'era', 'eremo', 'erete', 'eranno', 'erei', 'eresti',
            'erebbe', 'eremmo', 'ereste', 'erebbero', 'ino', 'iate', 'assi', 'asse', 'assimo', 'assero',
            'ando', 'ato', 'ata', 'ati', 'are',
        ],
        'ere' => [
            'o', 'i', 'e', 'iamo', 'ete', 'ono', 'evo', 'evi', 'eva', 'evamo', 'evate', 'evano', 'ei', 'esti',
            'emmo', 'este', 'erono', 'ero', 'erai', 'era', 'eremo', 'erete', 'eranno', 'erei', 'eresti',
            'erebbe', 'eremmo', 'ereste', 'erebbero', 'a', 'ano', 'iate', 'essi', 'esse', 'essimo', 'essero',
            'endo', 'uto', 'uta', 'uti', 'ute', 'ere',
        ],
        'ire' => [
            'o', 'i', 'e', 'iamo', 'ite', 'ono', 'ivo', 'ivi', 'iva', 'ivamo', 'ivate', 'ivano', 'ii', 'isti',
            'immo', 'iste', 'irono', 'iro', 'irai', 'ira', 'iremo', 'irete', 'iranno', 'irei', 'iresti',
            'irebbe', 'iremmo', 'ireste', 'irebbero', 'a', 'ano', 'iate', 'issi', 'isse', 'issimo', 'issero',
            'endo', 'ito', 'ita', 'iti', 'ire',
        ],
    ];
    protected const HOSTS = ['are' => ['ar', 'ando', 'a'], 'ere' => ['er', 'endo', 'i'], 'ire' => ['ir', 'endo', 'i']];
    protected const CLITICS = ['mi', 'ti', 'si', 'ci', 'vi', 'lo', 'la', 'li', 'le', 'gli', 'ne'];

    /** cercare→cerchi/cercherò, mangiare→mangi/mangerò. */
    protected static function spell(string $stem, string $ending, string $class): string
    {
        if ($class !== 'are' || ($ending[0] !== 'e' && $ending[0] !== 'i')) {
            return $stem;
        }

        return match (true) {
            str_ends_with($stem, 'c'), str_ends_with($stem, 'g') => $stem . 'h',
            (bool) preg_match('/[cg]i$/', $stem) => substr($stem, 0, -1),
            str_ends_with($stem, 'i') && $ending[0] === 'i' => substr($stem, 0, -1),
            default => $stem,
        };
    }
}
