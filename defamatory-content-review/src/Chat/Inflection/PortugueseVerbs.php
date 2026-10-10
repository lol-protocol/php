<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Conjugación regular del portugués para PortugueseInflection: tiempos
 * simples, infinitivo personal, participio, gerundio y pronombre pegado con
 * guion («matá-lo», «fode-me»: el guion se quita al buscar).
 */
final class PortugueseVerbs extends RomanceVerbs
{
    protected const ENDINGS = [
        'ar' => [
            'o', 'as', 'a', 'amos', 'ais', 'am', 'ei', 'aste', 'ou', 'astes', 'aram', 'ava', 'avas', 'avamos',
            'aveis', 'avam', 'arei', 'aras', 'ara', 'aremos', 'areis', 'arao', 'aria', 'arias', 'ariamos',
            'arieis', 'ariam', 'e', 'es', 'emos', 'eis', 'em', 'asse', 'asses', 'assemos', 'asseis', 'assem',
            'ando', 'ado', 'ada', 'ados', 'adas', 'ar', 'ares', 'armos', 'arem',
        ],
        'er' => [
            'o', 'es', 'e', 'emos', 'eis', 'em', 'i', 'este', 'eu', 'estes', 'eram', 'ia', 'ias', 'iamos', 'ieis',
            'iam', 'erei', 'eras', 'era', 'eremos', 'ereis', 'erao', 'eria', 'erias', 'eriamos', 'erieis',
            'eriam', 'a', 'as', 'amos', 'ais', 'am', 'esse', 'esses', 'essemos', 'esseis', 'essem', 'endo',
            'ido', 'ida', 'idos', 'idas', 'er', 'eres', 'ermos', 'erem',
        ],
        'ir' => [
            'o', 'es', 'e', 'imos', 'is', 'em', 'i', 'iste', 'iu', 'istes', 'iram', 'ia', 'ias', 'iamos', 'ieis',
            'iam', 'irei', 'iras', 'ira', 'iremos', 'ireis', 'irao', 'iria', 'irias', 'iriamos', 'irieis',
            'iriam', 'a', 'as', 'amos', 'ais', 'am', 'isse', 'isses', 'issemos', 'isseis', 'issem', 'indo',
            'ido', 'ida', 'idos', 'idas', 'ir', 'ires', 'irmos', 'irem',
        ],
    ];
    /** Infinitivo («matar-te»), infinitivo sin -r («matá-lo») y presente/imperativo («fode-me»). */
    protected const HOSTS = ['ar' => ['ar', 'a'], 'er' => ['er', 'e'], 'ir' => ['ir', 'e']];
    protected const CLITICS = ['me', 'te', 'se', 'nos', 'vos', 'lhe', 'lhes', 'lo', 'la', 'los', 'las'];

    /** ficar→fique, chegar→chegue, proteger→protejo. */
    protected static function spell(string $stem, string $ending, string $class): string
    {
        $last = substr($stem, -1);
        if ($class === 'ar' && $ending[0] === 'e') {
            return match ($last) {
                'c' => substr($stem, 0, -1) . 'qu',
                'g' => $stem . 'u',
                default => $stem,
            };
        }

        return $class !== 'ar' && $last === 'g' && ($ending[0] === 'o' || $ending[0] === 'a') ? substr($stem, 0, -1) . 'j' : $stem;
    }
}
