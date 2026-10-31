<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del búlgaro: plural y artículo pospuesto («идиот» → «идиоти», «идиотът»,
 * «идиотите»), y el adjetivo por género y número («тъп» → «тъпа», «тъпо», «тъпи»; los de «-ен» pierden la «е»
 * en unos («тъжен» → «тъжна») y no en otros («ален» → «алена»): se dan las dos). Ver SuffixInflection.
 */
final class BulgarianInflection extends SuffixInflection
{
    protected function nouns(): array
    {
        return [
            '/^(.+[^аеиоуъяю])$/u' => ['и', 'ове', 'а', 'ът', 'ят', 'ите', 'овете', 'ия', 'ите'],
            '/^(.+)а$/u' => ['а', 'ата', 'и', 'ите'],
            '/^(.+)я$/u' => ['я', 'ята', 'и', 'ите'],
            '/^(.+)о$/u' => ['о', 'ото', 'а', 'ата'],
            '/^(.+)е$/u' => ['е', 'ето', 'ета', 'етата'],
        ];
    }

    protected function adjectives(): array
    {
        $forms = ['а', 'о', 'и', 'ия', 'ият', 'ата', 'ото', 'ите'];

        return [
            '/^(.+)ен$/u' => [...array_map(fn(string $e): string => 'ен' . $e, $forms), ...array_map(fn(string $e): string => 'н' . $e, $forms)], // тъжен → тъжна; ален → алена
            '/^(.+[^аеиоуъяю])$/u' => $forms,
        ];
    }
}
