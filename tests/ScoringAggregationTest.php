<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\ScoringPolicy;
use PHPUnit\Framework\TestCase;

/** Modo de agregación 'max' (por defecto) vs. 'sum' — ScoringPolicy::aggregate(). */
class ScoringAggregationTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testSumAggregationAddsInsteadOfTakingTheWorst(): void
    {
        $max = ScoringPolicy::default();
        $sum = ScoringPolicy::default()->withAggregation('sum');

        $scores = [1.0, 1.0, 1.0];

        $this->assertSame(1.0, $max->aggregate($scores), 'max: el peor término manda.');
        $this->assertSame(3.0, $sum->aggregate($scores), 'sum: todos se acumulan.');
    }

    public function testAggregateOfEmptyScoresIsZeroRegardlessOfMode(): void
    {
        $this->assertSame(0.0, ScoringPolicy::default()->aggregate([]));
        $this->assertSame(0.0, ScoringPolicy::default()->withAggregation('sum')->aggregate([]));
    }

    public function testInvalidAggregationModeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ScoringPolicy::default()->withAggregation('average');
    }

    public function testRepeatedIdenticalTermIsNotCollapsedBySameLanguageDedup(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa', ScoringPolicy::default()->withAggregation('sum'));

        $once = $reviewer->validateName('puta');
        $twice = $reviewer->validateName('puta puta');

        $this->assertCount(1, $once->getFlaggedTerms());
        $this->assertCount(2, $twice->getFlaggedTerms(), 'Dos apariciones del mismo insulto no son una sola.');
        $this->assertSame($once->getScore() * 2, $twice->getScore());
    }
}
