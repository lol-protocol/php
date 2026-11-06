<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\InflectionLanguages;
use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\TestCase;

class InflectionLanguagesTest extends TestCase
{
    public function testTagsByLanguageKindAndFamily(): void
    {
        $this->assertNull(InflectionLanguages::tagFor('spa', 'noun'));
        $this->assertSame('infl', InflectionLanguages::tagFor('rus', 'noun'));
        $this->assertNull(InflectionLanguages::tagFor('rus', 'verb'));
        $this->assertNull(InflectionLanguages::tagFor('deu', 'noun'));
        $this->assertSame('infl', InflectionLanguages::tagFor('deu', 'adj'));
        $this->assertNull(InflectionLanguages::tagFor('xxx', 'noun'));
        $this->assertNull(InflectionLanguages::classFor('xxx'));
    }

    public function testTagalogLigatureHasItsOwnTag(): void
    {
        $this->assertSame(['tanga' => 'sg', 'tangang' => 'lig'], TopicInflector::tagged('tgl', 'tanga', 'noun'));
    }
}
