<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Casos del plural y casos menos comunes del húngaro (HungarianInflection), medidos contra UniMorph. */
class HungarianCasesTest extends TestCase
{
    /** @return array<string,array{string,list<string>}> lema, formas que deben estar */
    public static function nouns(): array
    {
        return [
            'casos del plural, vocal posterior' => ['barom', ['baromoknak', 'baromokkal', 'baromokban', 'baromokra', 'baromoktól']],
            'ilativo, adesivo, terminativo y formal' => ['barom', ['baromba', 'baromnál', 'baromig', 'baromként']],
            'translativo tras consonante' => ['barom', ['barommá', 'baromokká']],
            'vocal final: la «a» se alarga y el translativo lleva «v»' => ['kurva', ['kurvával', 'kurvává', 'kurvákban', 'kurvákkal']],
            'vocal anterior' => ['ember', ['emberek', 'emberekkel', 'emberbe', 'embernél', 'emberré']],
            'dígrafo doblado' => ['fasz', ['fasszal', 'fasszá']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('nouns')]
    public function testCaseForms(string $lemma, array $expected): void
    {
        $forms = TopicInflector::forms('hun', $lemma, 'noun');

        $this->assertSame([], array_diff($expected, $forms), $lemma);
        $this->assertSame($lemma, $forms[0]);
        $this->assertSame($forms, array_values(array_unique($forms)));
    }

    public function testVerbsStayLiteral(): void
    {
        $this->assertSame(['megy'], TopicInflector::forms('hun', 'megy', 'verb'));
    }
}
