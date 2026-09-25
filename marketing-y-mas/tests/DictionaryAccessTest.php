<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\LanguageRegistry;
use PHPUnit\Framework\TestCase;

/** WordList a través de $reviewer->languages(): normalización, filtros, estadísticas. */
class DictionaryAccessTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;
    private LanguageRegistry $registry;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
        $this->registry = $this->reviewer->languages()->registry();
    }

    public function testWordListNormalisation(): void
    {
        $list = $this->reviewer->languages()->wordList('spa');

        $this->assertSame($list->search('cerda'), $list->search('CERDA'));
        $this->assertSame($list->search('cerda'), $list->search('cérda'));
    }

    public function testGermanEszettFolding(): void
    {
        $list = $this->reviewer->languages()->wordList('deu');

        $this->assertNotNull($list->search('scheiße'));
        $this->assertSame($list->search('scheiße'), $list->search('scheisse'));
    }

    public function testFilteringByRiskType(): void
    {
        $animals = $this->reviewer->languages()->wordList('spa')->getByRiskType('animal');

        $this->assertNotEmpty($animals);
        foreach ($animals as $word) {
            $this->assertSame('animal', $word['riskType']);
        }
    }

    public function testStatisticsCoverEveryRiskType(): void
    {
        $stats = $this->reviewer->languages()->statistics('spa');

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
        foreach ($this->reviewer->languages()->byCoverage('comprehensive') as $code) {
            $this->assertGreaterThanOrEqual(
                200,
                $this->reviewer->languages()->wordList($code)->getWordCount(),
                "El diccionario '{$code}' se declara comprehensive pero tiene pocos términos."
            );
        }
    }

    public function testEveryDictionaryDeclaresValidRiskTypes(): void
    {
        $valid = array_keys(require self::CONFIG_DIR . '/risk-categories.php');

        foreach ($this->registry->getCodes() as $code) {
            foreach ($this->reviewer->languages()->wordList($code)->getAllWords() as $word) {
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
            foreach ($this->reviewer->languages()->wordList($code)->getAllWords() as $word) {
                $this->assertContains($word['severity'], ['low', 'medium', 'high']);
            }
        }
    }
}
