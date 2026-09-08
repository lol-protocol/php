<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\WordList;
use PHPUnit\Framework\TestCase;

class DefamatoryContentReviewTest extends TestCase
{
    private DefamatoryContentReviewer $reviewer;
    private WordList $wordList;

    protected function setUp(): void
    {
        $config = require __DIR__ . '/../config/defamatory-words.php';
        $this->wordList = new WordList($config);
        $this->reviewer = new DefamatoryContentReviewer($this->wordList);
    }

    public function testValidateCleanFullName(): void
    {
        $result = $this->reviewer->validateFullName('Juan', 'Pérez');

        $this->assertTrue($result->isValid());
        $this->assertEquals('none', $result->getSeverity());
        $this->assertEmpty($result->getFlaggedTerms());
    }

    public function testDetectInsultInFirstName(): void
    {
        $result = $this->reviewer->validateFullName('Idiota', 'García');

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getFlaggedTerms());
        $this->assertContains('insultos_personales', $result->getFlaggedCategories());
    }

    public function testDetectInsultInLastName(): void
    {
        $result = $this->reviewer->validateFullName('Zoila', 'Cerda');

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getFlaggedTerms());
    }

    public function testCaseInsensitiveDetection(): void
    {
        $result1 = $this->reviewer->validateFullName('ZOILA', 'CERDA');
        $result2 = $this->reviewer->validateFullName('zoila', 'cerda');
        $result3 = $this->reviewer->validateFullName('Zoila', 'Cerda');

        $this->assertFalse($result1->isValid());
        $this->assertFalse($result2->isValid());
        $this->assertFalse($result3->isValid());
    }

    public function testAccentInsensitiveDetection(): void
    {
        $result = $this->reviewer->validateName('cérda');

        $this->assertFalse($result->isValid());
    }

    public function testMultipleInsultsDetected(): void
    {
        $result = $this->reviewer->validateFullName('Idiota', 'Canalla');

        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->getFlaggedTerms());
    }

    public function testHighSeverityDetection(): void
    {
        $result = $this->reviewer->validateName('bastardo');

        $this->assertFalse($result->isValid());
        $this->assertEquals('high', $result->getSeverity());
    }

    public function testMediumSeverityDetection(): void
    {
        $result = $this->reviewer->validateName('idiota');

        $this->assertFalse($result->isValid());
        $this->assertEquals('medium', $result->getSeverity());
    }

    public function testBatchValidation(): void
    {
        $names = [
            'Juan Pérez',
            'Zoila Cerda',
            'María González',
        ];

        $results = $this->reviewer->batchValidateFullNames($names);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]->isValid());
        $this->assertFalse($results[1]->isValid());
        $this->assertTrue($results[2]->isValid());
    }

    public function testDetailedReport(): void
    {
        $result = $this->reviewer->validateFullName('Zoila', 'Cerda');
        $report = $this->reviewer->getDetailedReport($result);

        $this->assertArrayHasKey('name', $report);
        $this->assertArrayHasKey('valid', $report);
        $this->assertArrayHasKey('severity', $report);
        $this->assertArrayHasKey('recommendation', $report);
        $this->assertArrayHasKey('flaggedTerms', $report);
        $this->assertFalse($report['valid']);
    }

    public function testWordListNormalization(): void
    {
        $word1 = $this->wordList->search('cerda');
        $word2 = $this->wordList->search('CERDA');
        $word3 = $this->wordList->search('cérda');

        $this->assertNotNull($word1);
        $this->assertNotNull($word2);
        $this->assertNotNull($word3);
        $this->assertEquals($word1['original'], $word2['original']);
    }

    public function testFindInTextWithMultipleWords(): void
    {
        $matches = $this->wordList->findInText('Juan Idiota García Canalla');

        $this->assertCount(2, $matches);
    }

    public function testValidateNameWithSpaces(): void
    {
        $result = $this->reviewer->validateName('Juan Idiota');

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getFlaggedTerms());
    }

    public function testEmptyNameValidation(): void
    {
        $result = $this->reviewer->validateFullName('', '');

        $this->assertTrue($result->isValid());
    }

    public function testValidationResultArray(): void
    {
        $result = $this->reviewer->validateFullName('Zoila', 'Cerda');
        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Zoila Cerda', $array['fullName']);
        $this->assertFalse($array['isValid']);
        $this->assertGreaterThan(0, $array['totalFlagged']);
    }

    public function testCombinedCategories(): void
    {
        $result = $this->reviewer->validateFullName('Bastardo', 'Idiota');

        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->getFlaggedCategories());
    }
}
