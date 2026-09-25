<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

class PrimaryLanguageValidationTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
    }

    public function testCleanNameIsValid(): void
    {
        $result = $this->reviewer->validateFullName('Juan', 'Pérez');

        $this->assertTrue($result->isValid());
        $this->assertSame('none', $result->getSeverity());
        $this->assertEmpty($result->getFlaggedTerms());
    }

    public function testDetectsInsultInGivenName(): void
    {
        $result = $this->reviewer->validateFullName('Idiota', 'García');

        $this->assertFalse($result->isValid());
        $this->assertContains('intelectual', $result->getFlaggedRiskTypes());
    }

    public function testDetectsInsultInSurname(): void
    {
        $result = $this->reviewer->validateFullName('Zoila', 'Cerda');

        $this->assertFalse($result->isValid());
        $this->assertContains('animal', $result->getFlaggedRiskTypes());
    }

    public function testDetectionIsCaseInsensitive(): void
    {
        foreach (['CERDA', 'cerda', 'CeRdA'] as $variant) {
            $this->assertFalse($this->reviewer->validateName($variant)->isValid(), $variant);
        }
    }

    public function testDetectionFoldsDiacritics(): void
    {
        $this->assertFalse($this->reviewer->validateName('cérda')->isValid());
        $this->assertFalse($this->reviewer->validateName('IDIÓTA')->isValid());
    }

    public function testDetectsMultiWordTerm(): void
    {
        $result = $this->reviewer->validateName('Pedro Hijo De Puta Lopez');

        $this->assertFalse($result->isValid());
        $this->assertSame('high', $result->getSeverity());
        $this->assertCount(1, $result->getFlaggedTerms(), 'La frase cuenta como un término, no como tres.');
    }

    public function testSeverityComesFromTheWorstTerm(): void
    {
        $result = $this->reviewer->validateFullName('Bastardo', 'Tonto');

        $this->assertSame('high', $result->getSeverity());
        $this->assertCount(2, $result->getFlaggedTerms());
    }

    public function testEmptyNameIsValid(): void
    {
        $this->assertTrue($this->reviewer->validateFullName('', '')->isValid());
    }

    public function testBatchValidation(): void
    {
        $results = $this->reviewer->batchValidateFullNames(['Juan Pérez', 'Zoila Cerda', 'María González']);
        $this->assertCount(3, $results);
        $this->assertTrue($results[0]->isValid());
        $this->assertFalse($results[1]->isValid());
        $this->assertTrue($results[2]->isValid());
    }

    public function testMockingConstructionIsFlaggedAsBurlesco(): void
    {
        $result = $this->reviewer->validateFullName('Zurdo', 'Diestro');

        $this->assertFalse($result->isValid());
        $this->assertContains('burlesco', $result->getFlaggedRiskTypes());
        $this->assertSame('low', $result->getSeverity(), 'Aislados no son insulto: sólo marca.');
    }
}
