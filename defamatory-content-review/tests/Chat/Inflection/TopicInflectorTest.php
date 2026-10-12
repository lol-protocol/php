<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\TestCase;

class TopicInflectorTest extends TestCase
{
    private function entry(array $extra): array
    {
        return ['word' => 'desnudo', 'riskType' => 'sexual', 'severity' => 'medium'] + $extra;
    }

    public function testExpandsEveryFormKeepingTheFieldsOfTheEntry(): void
    {
        $out = TopicInflector::expand(['sexual' => [$this->entry(['forms' => 'adj'])]], 'spa');

        $this->assertSame(['desnudo', 'desnudos', 'desnuda', 'desnudas'], array_column($out['sexual'], 'word'));
        foreach ($out['sexual'] as $entry) {
            $this->assertSame(['word' => $entry['word'], 'riskType' => 'sexual', 'severity' => 'medium'], $entry);
        }
    }

    public function testAlsoAddsIrregularFormsByHand(): void
    {
        $out = TopicInflector::expand(['belico' => [$this->entry(['forms' => 'noun', 'also' => ['desnudez']])]], 'spa');

        $this->assertSame(['desnudo', 'desnudos', 'desnudez'], array_column($out['belico'], 'word'));
    }

    public function testAnEntryWithoutFormsStaysLiteral(): void
    {
        $out = TopicInflector::expand(['sexual' => [$this->entry([])]], 'spa');

        $this->assertSame(['desnudo'], array_column($out['sexual'], 'word'));
    }

    public function testALanguageWithoutInflectionKeepsTheLemma(): void
    {
        $out = TopicInflector::expand(['sexual' => [$this->entry(['forms' => 'noun'])]], 'deu');

        $this->assertSame(['desnudo'], array_column($out['sexual'], 'word'));
    }
}
