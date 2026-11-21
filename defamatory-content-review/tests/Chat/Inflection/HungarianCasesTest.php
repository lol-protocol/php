<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Casos del plural y casos menos comunes del húngaro (HungarianInflection), medidos contra UniMorph. */
class HungarianCasesTest extends TestCase
{
    use AssertsInflectedForms;

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
        $this->assertFormsInclude('hun', $lemma, 'noun', $expected);
    }

    public function testEverydayWordsThatCollideWithCasesPassThrough(): void
    {
        $chat = ChatLineReviewer::create(__DIR__ . '/../../../config', 'hun');

        foreach (['sértésekkel', 'sértésként', 'tettükért'] as $word) {
            $this->assertSame('approve', $chat->review("ez $word jó")->getDecision(), $word);
        }
        $this->assertSame('review', $chat->review('ez bolondokkal jó')->getDecision());
    }

    public function testVerbsStayLiteral(): void
    {
        $this->assertSame(['megy'], TopicInflector::forms('hun', 'megy', 'verb'));
    }
}
