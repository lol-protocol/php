<?php

namespace Tests\Chat;

use DefamatoryContentReview\RepeatedLetters;
use DefamatoryContentReview\RepeatedReadings;
use PHPUnit\Framework\TestCase;

class RepeatedReadingsTest extends TestCase
{
    public function testEachRunCanBeOneLetterOrTwo(): void
    {
        [$readings, $longest] = RepeatedReadings::of('follarr', []);

        $this->assertEqualsCanonicalizing(['folarr', 'folar', 'follar'], $readings);
        $this->assertSame(2, $longest);
    }

    public function testTheOriginalIsNeverAReading(): void
    {
        $this->assertNotContains('follarr', RepeatedReadings::of('follarr', [])[0]);
        $this->assertSame([['folar'], 2], RepeatedReadings::of('follar', []));
    }

    public function testAPhraseWithoutRunsHasNoReadings(): void
    {
        $this->assertSame([[], 0], RepeatedReadings::of('hijo de pa', []));
    }

    public function testReportsTheLongestRun(): void
    {
        $this->assertSame(4, RepeatedReadings::of('puuuuta mmierda', [])[1]);
    }

    public function testLegitWordsKeepTheirRunsAndTheRestStillGetsRead(): void
    {
        [$readings] = RepeatedReadings::of('calle puuuta', [RepeatedLetters::key('calle') => true]);

        $this->assertContains('calle puta', $readings);
        $this->assertNotContains('cale puta', $readings);
    }

    public function testWorksWithMultibyteLettersAndKeepsTheRestOfTheText(): void
    {
        $this->assertContains('un añejo', RepeatedReadings::of('un añññejo', [])[0]);
        $this->assertContains('ÑU ñu', RepeatedReadings::of('ÑÑU ññu', [])[0]);
    }

    public function testTooManyRunsOnlyTriesAllToOneAndAllToTwo(): void
    {
        $this->assertEqualsCanonicalizing(
            ['abcdefg', 'aabbccddeeffgg'],
            RepeatedReadings::of('aaabbbcccdddeeefffggg', [])[0]
        );
    }
}
