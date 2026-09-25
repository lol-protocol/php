<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * La decisión nunca rechaza en automático sólo por inferencia fonética —
 * es una señal de menor certeza que una coincidencia literal.
 */
class PhoneticFusionSeverityCapTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
    }

    public function testPhoneticOnlyHighSeverityCapsAtReviewNotReject(): void
    {
        $result = new ValidationResult('Prueba Prueba', true, 'spa');
        $result->addFlaggedTerm([
            'found' => 'bastardo',
            'category' => 'moral',
            'riskType' => 'moral',
            'severity' => 'high',
            'detectionMethod' => 'phonetic_fusion',
        ]);
        $result->setValid(false)->setSeverity('high');

        $this->assertTrue($result->hasOnlyPhoneticDetections());
        $this->assertSame('review', $this->reviewer->decide($result));
    }

    public function testLiteralHighSeverityIsStillRejected(): void
    {
        $result = $this->reviewer->validateFullName('Luis', 'Bastardo');

        $this->assertSame('high', $result->getSeverity());
        $this->assertFalse($result->hasOnlyPhoneticDetections());
        $this->assertSame('reject', $this->reviewer->decide($result));
    }

    public function testMixingLiteralAndPhoneticIsNotPhoneticOnly(): void
    {
        $result = new ValidationResult('x', false, 'spa');
        $result->addFlaggedTerm([
            'found' => 'idiota',
            'category' => 'intelectual',
            'riskType' => 'intelectual',
            'severity' => 'medium',
            'detectionMethod' => 'literal',
        ]);
        $result->addFlaggedTerm([
            'found' => 'vagina',
            'category' => 'ordinario',
            'riskType' => 'ordinario',
            'severity' => 'medium',
            'detectionMethod' => 'phonetic_fusion',
        ]);

        $this->assertFalse($result->hasOnlyPhoneticDetections());
    }

    /** No duplica lo que la búsqueda literal ya cubrió. */
    public function testLiterallyMatchedSurnameIsNotAlsoFlaggedAsVariant(): void
    {
        $result = $this->reviewer->validateFullName('Ana', 'Cerda');

        $this->assertEmpty($result->getPhoneticVariantTerms());
        $this->assertCount(1, $result->getFlaggedTerms());
    }
}
