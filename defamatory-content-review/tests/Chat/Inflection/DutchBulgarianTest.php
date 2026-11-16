<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Comparativo y superlativo del neerlandés (DutchInflection) y los adjetivos búlgaros en «-ски» (BulgarianInflection). */
class DutchBulgarianTest extends TestCase
{
    /** @return array<string,array{string,string,list<string>}> idioma, lema, formas que deben estar */
    public static function adjectives(): array
    {
        return [
            'neerlandés: vocal corta dobla la consonante' => ['nld', 'dom', ['domme', 'dommer', 'dommere', 'domste']],
            'neerlandés: -r usa -der' => ['nld', 'zwaar', ['zware', 'zwaarder', 'zwaardere', 'zwaarste']],
            'neerlandés: -s pasa a z y el superlativo lleva -te' => ['nld', 'vies', ['vieze', 'viezer', 'vieste']],
            'neerlandés: -k' => ['nld', 'lelijk', ['lelijke', 'lelijker', 'lelijkste']],
            'búlgaro: -ски' => ['bul', 'американски', ['американска', 'американско', 'американския', 'американските']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('adjectives')]
    public function testAdjectiveForms(string $language, string $lemma, array $expected): void
    {
        $forms = TopicInflector::forms($language, $lemma, 'adj');

        $this->assertSame([], array_diff($expected, $forms), $lemma);
        $this->assertSame($lemma, $forms[0]);
        $this->assertSame($forms, array_values(array_unique($forms)));
    }

    public function testDutchNounsKeepTheirPluralOnly(): void
    {
        $this->assertSame(['kut', 'kutten'], TopicInflector::forms('nld', 'kut', 'noun'));
    }
}
