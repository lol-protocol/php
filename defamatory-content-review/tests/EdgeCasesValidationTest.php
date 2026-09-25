<?php

namespace Tests;

use DefamatoryContentReview\LanguageAffinity;
use PHPUnit\Framework\TestCase;

class EdgeCasesValidationTest extends TestCase
{
    public function testLanguageAffinityRejectsInvalidPairFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Pair format must be 'a|b'");

        new LanguageAffinity(['invalid' => 0.8], 0.70);
    }

    public function testLanguageAffinityRejectsMultipleBars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LanguageAffinity(['a|b|c' => 0.8], 0.70);
    }

    public function testLanguageAffinityAcceptsValidFormat(): void
    {
        $affinity = new LanguageAffinity(['a|b' => 0.8], 0.70);
        $this->assertSame(0.8, $affinity->between('a', 'b'));
        $this->assertSame(0.8, $affinity->between('b', 'a'));
    }
}
