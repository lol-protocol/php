<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del rumano: plural con palatalización («idiot» → «idioți», «prost» → «proști»),
 * artículo pospuesto («idiotul», «idiotului»), vocativo («idiotule») y el adjetivo por género y número.
 * Las clases que acaban en consonante palatalizable se generan de una tabla (terminación => plural);
 * medidas contra UniMorph con bin/inflection-check.php. Ver SuffixInflection.
 */
final class RomanianInflection extends SuffixInflection
{
    /** terminación => [plural palatalizado, ¿lleva «-urilor» en el sustantivo?] */
    private const PALATAL = [
        'st' => ['ști', false], 't' => ['ți', true], 'd' => ['zi', false], 's' => ['și', true],
        'z' => ['ji', false], 'c' => ['ci', true], 'g' => ['gi', false],
    ];
    /** Adjetivos: las clases que además llevan femenino articulado («-stele», «-stei»). */
    private const ARTICULATED = ['st', 't', 'd', 'c'];

    protected function nouns(): array
    {
        $rules = [];
        foreach (self::PALATAL as $end => [$plural, $uri]) {
            $rules["/^(.+){$end}$/u"] = [$plural, $end . 'ul', $end . 'ului', $end . 'ule', $plural . 'i', $plural . 'lor', $end . 'uri', ...($uri ? [$end . 'urilor'] : [])];
        }

        return $rules + [
            '/^(.+[nlrmbpfv])$/u' => ['i', 'ul', 'ului', 'ule', 'ii', 'ilor', 'uri'],
            '/^(.+)ă$/u' => ['a', 'ei', 'elor', 'ele', 'e', 'i'], // «târfă» → «târfe»: el plural indefinido casi no está en UniMorph
            '/^(.+)e$/u' => ['ea', 'a', 'ilor', 'ile', 'ii', 'i', 'ei'],
            '/^(.+)a$/u' => ['ale', 'ele', 'ei'],
            '/^(.+)u$/u' => ['ul', 'ului', 'ule', 'ilor', 'ii', 'elor', 'ele'],
        ];
    }

    protected function adjectives(): array
    {
        $rules = [];
        foreach (self::PALATAL as $end => [$plural]) {
            if ($end === 'z' || $end === 'g') {
                continue;
            }
            $rules["/^(.+){$end}$/u"] = [$end . 'ă', $end . 'e', $plural, $end . 'ul', $end . 'ului', $plural . 'i', $plural . 'lor',
                ...(in_array($end, self::ARTICULATED, true) ? [$end . 'ele', $end . 'elor', $end . 'ei', $end . 'a'] : [])];
        }
        // Orden de las reglas: «-u» antes de la consonante general, y «-s» antes que ella.
        return $rules + [
            '/^(.+)u$/u' => ['ul', 'ului', 'ii', 'a', 'ei', 'e', 'i', 'ilor', 'elor', 'ele', 'ă'],
            '/^(.+[bglmnprvfz])$/u' => ['ă', 'e', 'i', 'ul', 'ului', 'ii', 'ilor', 'elor', 'ele', 'ei', 'a'],
        ];
    }
}
