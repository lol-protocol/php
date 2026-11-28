<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Adjetivos del sueco, el danés y el noruego, medidos contra UniMorph (sueco 53 → 78 %, danés 59 → 65 %, noruego 51 → 57 %). */
class NordicAdjectivesTest extends TestCase
{
    use AssertsInflectedForms;

    /** @return array<string,array{string,string,list<string>}> idioma, lema, formas que deben estar */
    public static function adjectives(): array
    {
        return [
            'sueco participio en -ad' => ['swe', 'korkad', ['korkat', 'korkade']],
            'sueco en -d' => ['swe', 'blind', ['blint', 'blinda']],
            'sueco en -en' => ['swe', 'naken', ['naket', 'nakna', 'nakne']],
            'sueco regular' => ['swe', 'dum', ['dumma', 'dumt']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('adjectives')]
    public function testAdjectiveForms(string $language, string $lemma, array $expected): void
    {
        $this->assertFormsInclude($language, $lemma, 'adj', $expected);
    }

    /** @return array<string,array{string,string}> */
    public static function invariableAndNeuter(): array
    {
        return [
            'sueco en -e' => ['swe', 'moderne'],
            'danés en -e' => ['dan', 'gule'],
            'noruego en -e' => ['nor', 'moderne'],
        ];
    }

    #[DataProvider('invariableAndNeuter')]
    public function testAdjectivesInEDoNotChange(string $language, string $lemma): void
    {
        $this->assertSame([$lemma], TopicInflector::forms($language, $lemma, 'adj'));
    }

    public function testAdjectivesInTDoNotGetASecondT(): void
    {
        $this->assertNotContains('söttt', TopicInflector::forms('swe', 'sött', 'adj'));
        $this->assertNotContains('sortt', TopicInflector::forms('dan', 'sort', 'adj'));
    }
}
