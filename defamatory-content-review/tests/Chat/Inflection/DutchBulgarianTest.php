<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Comparativo y superlativo del neerlandés (DutchInflection) y los adjetivos búlgaros en «-ски» (BulgarianInflection). */
class DutchBulgarianTest extends TestCase
{
    use AssertsInflectedForms;

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
        $this->assertFormsInclude($language, $lemma, 'adj', $expected);
    }

    public function testDutchNounsKeepTheirPluralOnly(): void
    {
        $this->assertSame(['kut', 'kutten'], TopicInflector::forms('nld', 'kut', 'noun'));
    }
}
