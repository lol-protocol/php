<?php

namespace Tests;

use DefamatoryContentReview\ChatPatternMatcher;
use PHPUnit\Framework\TestCase;

class ChatPatternMatcherTest extends TestCase
{
    private const THREAT = ['pattern' => '\bte\s+voy\s+a\s+matar\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'amenaza'];

    public function testFindsAPhraseAndReturnsItAsWritten(): void
    {
        $found = ChatPatternMatcher::find('¡Ya verás! Te voy a MATAR, Ñoño', [self::THREAT]);

        $this->assertSame([[
            'original' => 'amenaza', 'category' => 'patterns', 'riskType' => 'belico',
            'severity' => 'high', 'nameCollision' => false, 'found' => 'Te voy a MATAR',
        ]], $found);
    }

    public function testThePositionIsRightAfterAccentsAndEnyes(): void
    {
        $pattern = ['pattern' => '\bmatalos\b', 'riskType' => 'belico', 'severity' => 'medium'];

        $this->assertSame('mátalos', ChatPatternMatcher::find('Mañana ñandú mátalos ya', [$pattern])[0]['found']);
        $this->assertSame('\bmatalos\b', ChatPatternMatcher::find('mátalos', [$pattern])[0]['original'], 'sin label, el patrón hace de nombre');
    }

    public function testFoldsLeetBeforeMatching(): void
    {
        $this->assertSame('T3 V0Y A M4T4R', ChatPatternMatcher::find('T3 V0Y A M4T4R', [self::THREAT])[0]['found']);
    }

    public function testCountsEveryOccurrence(): void
    {
        $this->assertCount(2, ChatPatternMatcher::find('te voy a matar y te voy a matar', [self::THREAT]));
    }

    public function testNoMatchReturnsNothing(): void
    {
        $this->assertSame([], ChatPatternMatcher::find('te voy a llevar', [self::THREAT]));
        $this->assertSame([], ChatPatternMatcher::find('te voy a matar', []));
    }

    public function testALineWhoseFoldChangesLengthFallsBackToTheFoldedText(): void
    {
        $this->assertSame('te voy a matar', ChatPatternMatcher::find('STRAßE TE VOY A MATAR', [self::THREAT])[0]['found']);
    }

    public function testFoldLowersFoldsAndSpacesOutPunctuation(): void
    {
        $this->assertSame('matalos  arbol i ', ChatPatternMatcher::fold('Mátalos, ÁRBOL 1!'));
    }

    public function testFoldKeepsOnePositionPerCharacter(): void
    {
        $line = 'Mañana, ¿qué tal? 😀';

        $this->assertSame(mb_strlen($line), strlen(ChatPatternMatcher::fold($line)));
    }
}
