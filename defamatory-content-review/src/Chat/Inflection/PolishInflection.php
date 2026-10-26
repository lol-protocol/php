<?php

namespace DefamatoryContentReview\Chat\Inflection;

/** Declinación regular del polaco: sustantivos masculinos, femeninos y neutros, adjetivos en -y/-i y verbos en -ać. Ver SuffixInflection. */
final class PolishInflection extends SuffixInflection
{
    protected function nouns(): array
    {
        return [
            '/^(.+)ek$/u' => ['ek', 'ka', 'kowi', 'kiem', 'ku', 'ki', 'kowie', 'ków', 'kom', 'kami', 'kach'], // dupek → dupka
            '/^(.+[kg])$/u' => ['a', 'owi', 'iem', 'u', 'i', 'owie', 'ów', 'om', 'ami', 'ach'],
            '/^(.+[bdfhjlłmnprstwzżźćńśó])$/u' => ['a', 'owi', 'em', 'ie', 'u', 'y', 'i', 'owie', 'ów', 'om', 'ami', 'ach', 'e'],
            '/^(.+)ka$/u' => ['ki', 'ce', 'kę', 'ką', 'ko', 'kom', 'kami', 'kach', 'ek', 'k'],
            '/^(.+)ga$/u' => ['gi', 'dze', 'gę', 'gą', 'go', 'gom', 'gami', 'gach', 'g'],
            '/^(.+)[ij]a$/u' => ['i', 'ię', 'ią', 'io', 'iom', 'iami', 'iach', 'ji'],
            '/^(.+)a$/u' => ['y', 'ę', 'ą', 'ie', 'o', 'om', 'ami', 'ach', 'i', ''],
            '/^(.+)o$/u' => ['a', 'u', 'em', 'e', 'om', 'ami', 'ach', ''],
            '/^(.+)e$/u' => ['a', 'u', 'em', 'om', 'ami', 'ach', 'y'],
        ];
    }

    protected function adjectives(): array
    {
        return [
            '/^(.+[kg])i$/u' => ['i', 'iego', 'iemu', 'im', 'ia', 'iej', 'ią', 'ie', 'ich', 'imi'],
            '/^(.+)i$/u' => ['i', 'iego', 'iemu', 'im', 'ia', 'iej', 'ią', 'ie', 'y', 'ich', 'imi'],
            '/^(.+)y$/u' => ['y', 'ego', 'emu', 'ym', 'a', 'ej', 'ą', 'e', 'i', 'ych', 'ymi'],
        ];
    }

    protected function verb(string $lemma): array
    {
        if (preg_match('/^(.+)ać$/u', $lemma, $m) !== 1) {
            return [$lemma];
        }

        return array_map(fn(string $ending): string => $m[1] . $ending, ['ać', 'am', 'asz', 'a', 'amy', 'acie', 'ają', 'ał', 'ała', 'ało', 'ali', 'ały', 'aj', 'ajcie']);
    }
}
