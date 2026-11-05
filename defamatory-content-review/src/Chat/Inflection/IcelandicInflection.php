<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del islandés (solo sustantivos; UniMorph no trae adjetivos): caso y número con el
 * artículo pospuesto («hundur» → «hundi», «hundar», «hundinn»; «skepna» → «skepnu», «skepnan»).
 * El género no se sabe por la forma, así que cada terminación da el conjunto más frecuente de las
 * suyas, medido con bin/inflection-check.php. Ver SuffixInflection.
 */
final class IcelandicInflection extends SuffixInflection
{
    protected function nouns(): array
    {
        return [
            '/^(.+)ur$/u' => ['', 'inn', 'urinn', 's', 'i', 'a', 'anna', 'ar', 'um', 'sins'],
            '/^(.+l)l$/u' => ['', 'inn', 's', 'sins'],
            '/^(.+)a$/u' => ['u', 'una', 'unni', 'unnar', 'um', 'unum', 'urnar', 'ur', 'an', 'anna'],
            '/^(.+)i$/u' => ['a', 'anna', 'ar', 'ann', 'ans', 'anum', 'ana', 'arnir', 'um', 'inn'],
            '/^(.+(?:ing|ung))$/u' => ['in', 'a', 'anna', 'arinnar', 'ar', 'una', 'u', 'unni', 'um', 'unum'],
            '/^(.+un)$/u' => ['in', 'arinnar', 'ar', 'ina', 'inni', 'um', 'unum'],
            '/^(.+(?:nd|rð|tt|st|ld))$/u' => ['in', 'a', 'anna', 'ina', 'inni', 'arinnar', 'ar', 'um', 'unum', 'ið', 'i', 'inu', 'sins', 's'],
            '/^(.+[^aeiouyáéíóúýæö])$/u' => ['s', 'sins', 'ið', 'i', 'inu', 'a', 'anna', 'um', 'unum', 'in', 'ar'],
        ];
    }

    protected function adjectives(): array
    {
        return [];
    }
}
