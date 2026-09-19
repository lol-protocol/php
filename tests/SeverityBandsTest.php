<?php

namespace Tests;

use DefamatoryContentReview\ScoringPolicy;
use PHPUnit\Framework\TestCase;

/** Cortes de banda de severidad — SeverityBands, vía ScoringPolicy. */
class SeverityBandsTest extends TestCase
{
    public function testCustomBandsChangeSeverityBoundaries(): void
    {
        // Umbrales mucho más exigentes que el default (2.5/1.5): hace
        // falta más puntaje para llegar a 'high' o 'medium'.
        $policy = ScoringPolicy::default()->withBands([
            [5.0, 'high'],
            [3.0, 'medium'],
            [0.0, 'low'],
        ]);

        $this->assertSame('low', $policy->severityFromScore(2.0), 'Con las bandas por defecto esto sería medium.');
        $this->assertSame('medium', $policy->severityFromScore(4.0));
        $this->assertSame('high', $policy->severityFromScore(5.0));
        $this->assertSame('none', $policy->severityFromScore(0.0));
    }

    public function testBandsAreSortedRegardlessOfInputOrder(): void
    {
        $policy = ScoringPolicy::default()->withBands([
            [0.0, 'low'],
            [2.5, 'high'],
            [1.5, 'medium'],
        ]);

        $this->assertSame([[2.5, 'high'], [1.5, 'medium'], [0.0, 'low']], $policy->getBands());
    }
}
