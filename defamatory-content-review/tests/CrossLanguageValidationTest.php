<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\LanguageRegistry;
use PHPUnit\Framework\TestCase;

class CrossLanguageValidationTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;
    private LanguageRegistry $registry;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
        $this->registry = $this->reviewer->languages()->registry();
    }

    public function testCrossLanguageFindsTermFromARelatedLanguage(): void
    {
        // "porco" es portugués, no español: sólo aparece al cruzar.
        $this->assertTrue($this->reviewer->validateName('João Porco')->isValid());

        $crossed = $this->reviewer->related()->validate('João Porco');

        $this->assertFalse($crossed->isValid());
        $this->assertSame(['por'], array_column($crossed->getFlaggedTerms(), 'sourceLanguage'));
    }

    public function testCrossLanguageMatchCarriesReducedConfidence(): void
    {
        $result = $this->reviewer->related()->validate('Marco Stronzo');
        $term = $result->getFlaggedTerms()[0];

        $this->assertSame('ita', $term['sourceLanguage']);
        $this->assertLessThan(1.0, $term['confidence']);
        $this->assertSame($this->registry->getAffinity('spa', 'ita'), $term['confidence']);
    }

    public function testConfidenceDownweightsSeverity(): void
    {
        // "stronzo" es 'high' en italiano; a 0.82 de afinidad baja a 'medium'.
        $this->assertSame('high', $this->reviewer->languages()->wordList('ita')->search('stronzo')['severity']);
        $this->assertSame('medium', $this->reviewer->related()->validate('Marco Stronzo')->getSeverity());
    }

    public function testPrimaryLanguageMatchKeepsFullConfidence(): void
    {
        $result = $this->reviewer->related()->validate('Luis Bastardo');
        $primary = $result->getPrimaryLanguageTerms();

        $this->assertNotEmpty($primary);
        $this->assertSame(1.0, $primary[0]['confidence']);
        $this->assertSame('high', $result->getSeverity());
    }

    public function testCrossLanguageRecordsWhichLanguagesWereChecked(): void
    {
        $checked = $this->reviewer->related()->validate('Juan Pérez')->getLanguagesChecked();

        $this->assertArrayHasKey('spa', $checked);
        $this->assertArrayHasKey('por', $checked);
        $this->assertSame(1.0, $checked['spa']);
    }

    public function testExplicitLanguageSetIgnoresAffinityModel(): void
    {
        $result = $this->reviewer->validateInLanguages('Hans Scheisse', ['spa', 'deu']);

        $this->assertFalse($result->isValid());
        $this->assertSame('high', $result->getSeverity(), 'Sin descuento: confianza 1.0 en ambos idiomas.');
    }

    public function testSwitchingLanguageChangesTheDictionary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'eng');

        $this->assertTrue($reviewer->validateName('cerda')->isValid(), 'No es palabra inglesa.');
        $this->assertFalse($reviewer->validateName('bastard')->isValid());
    }
}
