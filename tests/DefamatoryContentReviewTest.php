<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\LanguageRegistry;
use DefamatoryContentReview\WordList;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DefamatoryContentReviewTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;
    private LanguageRegistry $registry;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
        $this->registry = $this->reviewer->getRegistry();
    }

    // -----------------------------------------------------------------
    // Códigos de idioma ISO 639-3
    // -----------------------------------------------------------------

    public function testResolvesThreeLetterCode(): void
    {
        $this->assertSame('spa', $this->registry->resolve('spa'));
        $this->assertSame('deu', $this->registry->resolve('deu'));
    }

    public function testAcceptsTwoLetterCodeAsAlias(): void
    {
        $this->assertSame('spa', $this->registry->resolve('es'));
        $this->assertSame('deu', $this->registry->resolve('de'));
        $this->assertSame('zho', $this->registry->resolve('zh'));
        $this->assertSame('ell', $this->registry->resolve('el'));
    }

    public function testResolveIsCaseInsensitive(): void
    {
        $this->assertSame('spa', $this->registry->resolve('SPA'));
        $this->assertSame('por', $this->registry->resolve('PT'));
    }

    public function testUnknownCodeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->registry->resolve('xyz');
    }

    public function testAllThirtyLanguagesAreRegistered(): void
    {
        $this->assertCount(30, $this->registry->getCodes());
    }

    public function testEveryRegisteredLanguageHasADictionaryFile(): void
    {
        foreach ($this->registry->getCodes() as $code) {
            $this->assertFileExists(
                self::CONFIG_DIR . "/languages/{$code}.php",
                "Falta el diccionario de '{$code}'."
            );
        }
    }

    public function testEveryDictionaryDeclaresItsOwnCode(): void
    {
        foreach ($this->registry->getCodes() as $code) {
            $this->assertSame($code, $this->reviewer->getWordList($code)->getLanguage());
        }
    }

    // -----------------------------------------------------------------
    // Parentesco entre idiomas
    // -----------------------------------------------------------------

    public function testAffinityIsSymmetric(): void
    {
        $this->assertSame(
            $this->registry->getAffinity('spa', 'por'),
            $this->registry->getAffinity('por', 'spa')
        );
    }

    public function testAffinityWithItselfIsOne(): void
    {
        $this->assertSame(1.0, $this->registry->getAffinity('rus', 'rus'));
    }

    public function testUnrelatedLanguagesHaveZeroAffinity(): void
    {
        $this->assertSame(0.0, $this->registry->getAffinity('spa', 'jpn'));
    }

    public function testRelatedLanguagesAreSortedByAffinity(): void
    {
        $related = $this->registry->getRelated('spa');

        $this->assertArrayHasKey('por', $related);
        $this->assertSame('por', array_key_first($related), 'Portugués es el más cercano al español.');
        $this->assertSame($related, array_slice($related, 0, count($related), true));
        $this->assertGreaterThanOrEqual(array_values($related)[1], $related['por']);
    }

    public function testRussianAndUkrainianAreAssociated(): void
    {
        $this->assertArrayHasKey('ukr', $this->registry->getRelated('rus'));
        $this->assertArrayHasKey('rus', $this->registry->getRelated('ukr'));
    }

    public function testThresholdNarrowsTheRelatedSet(): void
    {
        $wide = $this->registry->getRelated('spa', 0.60);
        $narrow = $this->registry->getRelated('spa', 0.85);

        $this->assertGreaterThan(count($narrow), count($wide));
        $this->assertArrayHasKey('por', $narrow);
        $this->assertArrayNotHasKey('ron', $narrow);
    }

    public function testValidationSetIncludesTheLanguageItself(): void
    {
        $set = $this->registry->getValidationSet('spa');

        $this->assertSame(1.0, $set['spa']);
        $this->assertArrayHasKey('por', $set);
    }

    public function testFamilyMembersExcludeTheLanguageItself(): void
    {
        $members = $this->registry->getFamilyMembers('ces');

        $this->assertContains('slk', $members);
        $this->assertNotContains('ces', $members);
    }

    // -----------------------------------------------------------------
    // Validación en el idioma principal
    // -----------------------------------------------------------------

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
        $results = $this->reviewer->batchValidateFullNames([
            'Juan Pérez',
            'Zoila Cerda',
            'María González',
        ]);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]->isValid());
        $this->assertFalse($results[1]->isValid());
        $this->assertTrue($results[2]->isValid());
    }

    // -----------------------------------------------------------------
    // Ridiculización
    // -----------------------------------------------------------------

    public function testMockingConstructionIsFlaggedAsBurlesco(): void
    {
        $result = $this->reviewer->validateFullName('Zurdo', 'Diestro');

        $this->assertFalse($result->isValid());
        $this->assertContains('burlesco', $result->getFlaggedRiskTypes());
        $this->assertSame('low', $result->getSeverity(), 'Aislados no son insulto: sólo marca.');
    }

    // -----------------------------------------------------------------
    // Colisión con apellidos legítimos
    // -----------------------------------------------------------------

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

    // -----------------------------------------------------------------
    // Validación cruzada entre idiomas asociados
    // -----------------------------------------------------------------

    public function testCrossLanguageFindsTermFromARelatedLanguage(): void
    {
        // "porco" es portugués, no español: sólo aparece al cruzar.
        $this->assertTrue($this->reviewer->validateName('João Porco')->isValid());

        $crossed = $this->reviewer->validateAcrossRelated('João Porco');

        $this->assertFalse($crossed->isValid());
        $this->assertSame(['por'], array_column($crossed->getFlaggedTerms(), 'sourceLanguage'));
    }

    public function testCrossLanguageMatchCarriesReducedConfidence(): void
    {
        $result = $this->reviewer->validateAcrossRelated('Marco Stronzo');
        $term = $result->getFlaggedTerms()[0];

        $this->assertSame('ita', $term['sourceLanguage']);
        $this->assertLessThan(1.0, $term['confidence']);
        $this->assertSame($this->registry->getAffinity('spa', 'ita'), $term['confidence']);
    }

    public function testConfidenceDownweightsSeverity(): void
    {
        // "stronzo" es 'high' en italiano; a 0.82 de afinidad baja a 'medium'.
        $this->assertSame('high', $this->reviewer->getWordList('ita')->search('stronzo')['severity']);
        $this->assertSame('medium', $this->reviewer->validateAcrossRelated('Marco Stronzo')->getSeverity());
    }

    public function testPrimaryLanguageMatchKeepsFullConfidence(): void
    {
        $result = $this->reviewer->validateAcrossRelated('Luis Bastardo');
        $primary = $result->getPrimaryLanguageTerms();

        $this->assertNotEmpty($primary);
        $this->assertSame(1.0, $primary[0]['confidence']);
        $this->assertSame('high', $result->getSeverity());
    }

    public function testCrossLanguageRecordsWhichLanguagesWereChecked(): void
    {
        $checked = $this->reviewer->validateAcrossRelated('Juan Pérez')->getLanguagesChecked();

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

    // -----------------------------------------------------------------
    // WordList
    // -----------------------------------------------------------------

    public function testWordListNormalisation(): void
    {
        $list = $this->reviewer->getWordList('spa');

        $this->assertSame($list->search('cerda'), $list->search('CERDA'));
        $this->assertSame($list->search('cerda'), $list->search('cérda'));
    }

    public function testGermanEszettFolding(): void
    {
        $list = $this->reviewer->getWordList('deu');

        $this->assertNotNull($list->search('scheiße'));
        $this->assertSame($list->search('scheiße'), $list->search('scheisse'));
    }

    public function testFilteringByRiskType(): void
    {
        $animals = $this->reviewer->getWordList('spa')->getByRiskType('animal');

        $this->assertNotEmpty($animals);
        foreach ($animals as $word) {
            $this->assertSame('animal', $word['riskType']);
        }
    }

    public function testStatisticsCoverEveryRiskType(): void
    {
        $stats = $this->reviewer->getWordListStatistics('spa');

        $expected = [
            'animal', 'intelectual', 'discapacidad', 'fisico', 'moral',
            'genero', 'ordinario', 'burlesco', 'etnico', 'religioso',
        ];

        foreach ($expected as $riskType) {
            $this->assertArrayHasKey($riskType, $stats['byRiskType'], "Falta el tipo '{$riskType}'.");
        }
    }

    public function testComprehensiveDictionariesAreSubstantial(): void
    {
        foreach ($this->reviewer->getLanguagesByCoverage('comprehensive') as $code) {
            $this->assertGreaterThanOrEqual(
                200,
                $this->reviewer->getWordList($code)->getWordCount(),
                "El diccionario '{$code}' se declara comprehensive pero tiene pocos términos."
            );
        }
    }

    public function testEveryDictionaryDeclaresValidRiskTypes(): void
    {
        $valid = array_keys(require self::CONFIG_DIR . '/risk-categories.php');

        foreach ($this->registry->getCodes() as $code) {
            foreach ($this->reviewer->getWordList($code)->getAllWords() as $word) {
                $this->assertContains(
                    $word['riskType'],
                    $valid,
                    "'{$word['original']}' ({$code}) declara un tipo de riesgo desconocido."
                );
            }
        }
    }

    public function testEveryDictionaryDeclaresValidSeverities(): void
    {
        foreach ($this->registry->getCodes() as $code) {
            foreach ($this->reviewer->getWordList($code)->getAllWords() as $word) {
                $this->assertContains($word['severity'], ['low', 'medium', 'high']);
            }
        }
    }

    // -----------------------------------------------------------------
    // Informes
    // -----------------------------------------------------------------

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
