<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

/** Colisión con apellidos legítimos y forma del reporte detallado. */
class DecisionOutcomesTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
    }

    public function testHighSeverityWithNameCollisionGoesToReviewNotReject(): void
    {
        $result = $this->reviewer->validateFullName('Juan', 'Moro');

        $this->assertSame('high', $result->getSeverity());
        $this->assertTrue($result->hasNameCollision());
        $this->assertSame('review', $this->reviewer->decide($result));
    }

    public function testHighSeverityWithoutCollisionIsRejected(): void
    {
        $result = $this->reviewer->validateFullName('Luis', 'Bastardo');

        $this->assertSame('high', $result->getSeverity());
        $this->assertFalse($result->hasNameCollision());
        $this->assertSame('reject', $this->reviewer->decide($result));
    }

    public function testCleanNameIsAccepted(): void
    {
        $result = $this->reviewer->validateFullName('María', 'González');

        $this->assertSame('accept', $this->reviewer->decide($result));
    }

    public function testDetailedReportShape(): void
    {
        $report = $this->reviewer->getDetailedReport(
            $this->reviewer->validateFullName('Zoila', 'Cerda')
        );

        foreach (['fullName', 'language', 'severity', 'decision', 'recommendation', 'riskAnalysis'] as $key) {
            $this->assertArrayHasKey($key, $report);
        }

        $this->assertFalse($report['isValid']);
        $this->assertArrayHasKey('animal', $report['riskAnalysis']);
        $this->assertSame(['spa'], $report['riskAnalysis']['animal']['languages']);
    }

    public function testResultSerialisesToArray(): void
    {
        $array = $this->reviewer->validateFullName('Zoila', 'Cerda')->toArray();

        $this->assertSame('Zoila Cerda', $array['fullName']);
        $this->assertSame('spa', $array['language']);
        $this->assertFalse($array['isValid']);
        $this->assertGreaterThan(0, $array['totalFlagged']);
        $this->assertTrue($array['hasNameCollision']);
    }
}
