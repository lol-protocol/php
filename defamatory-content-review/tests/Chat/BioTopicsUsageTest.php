<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Http\TextMarks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Los temas de biografía (`config/bio-topics/`) en uso: que una biografía común no se marque, que sigan
 * valiendo el resto de revisiones, y cómo se piden desde `TextMarks`. Los casos de «lo que alguien dice de
 * sí mismo» están en BioTopicsTest.
 */
class BioTopicsUsageTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string}> */
    public static function cleanBios(): array
    {
        return [
            'genealogista' => ['spa', 'Genealogista aficionada y archivera.'],
            'profesión' => ['spa', 'Soy historiador y transcribo actas del siglo XIX'],
            'soy + adjetivo común' => ['spa', 'Soy una persona curiosa y organizada'],
            'tiene, de un tercero' => ['spa', 'Mi abuelo tenía un molino'],
            'archivist' => ['eng', 'Family historian and archivist'],
            'I am + profession' => ['eng', 'I am a librarian in Santiago'],
            'I have + common' => ['eng', 'I have two cats and a large family tree'],
        ];
    }

    #[DataProvider('cleanBios')]
    public function testBioModeDoesNotFlagAnOrdinaryBio(string $language, string $text): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, $language, 'bio-topics')->review($text);

        $this->assertSame('approve', $result->getDecision(), $text);
        $this->assertSame([], $result->getMatches(), $text);
    }

    public function testBioModeKeepsFlaggingPersonalDataAndInsults(): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, 'spa', 'bio-topics')->review('Mi RUT es 12.345.678-5');

        $this->assertContains('dato personal (RUT)', array_column($result->getMatches(), 'original'));
    }

    public function testTextMarksTakesTheTopicsFolder(): void
    {
        $normal = TextMarks::of('Soy evangélica y tengo diabetes', self::CONFIG_DIR, ['spa', 'eng']);
        $bio = TextMarks::of('Soy evangélica y tengo diabetes', self::CONFIG_DIR, ['spa', 'eng'], 'bio-topics');

        $this->assertSame(['approve', []], [$normal['decision'], $normal['marks']]);
        $this->assertSame('review', $bio['decision']);
        $this->assertEqualsCanonicalizing(['dato sensible (religión)', 'dato sensible (salud)'], $bio['marks']);
    }

    public function testTextMarksKeepsEachFolderApartInItsCache(): void
    {
        $first = TextMarks::of('Tengo cáncer', self::CONFIG_DIR, ['spa'], 'bio-topics');
        $second = TextMarks::of('Tengo cáncer', self::CONFIG_DIR, ['spa']);
        $third = TextMarks::of('Tengo cáncer', self::CONFIG_DIR, ['spa'], 'bio-topics');

        $this->assertSame('review', $first['decision']);
        $this->assertSame('approve', $second['decision'], 'pedir primero bio-topics no debe contaminar al modo normal');
        $this->assertSame($first, $third);
    }

    public function testALanguageWithoutBioTopicsFlagsNothingAndDoesNotFail(): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, 'fra', 'bio-topics')->review("J'ai un cancer");

        $this->assertSame('approve', $result->getDecision());
    }

    public function testTheBioTopicsFilesDeclareTheirKind(): void
    {
        foreach (['spa', 'eng'] as $language) {
            $config = require self::CONFIG_DIR . "/bio-topics/{$language}.php";

            $this->assertSame(['code' => $language, 'kind' => 'bio-topics', 'collapseRepeats' => false], $config['meta']);
            $this->assertNotEmpty($config['patterns']);
            foreach ($config['patterns'] as $pattern) {
                $this->assertSame('privado', $pattern['riskType']);
                $this->assertSame('medium', $pattern['severity']);
                $this->assertNotFalse(@preg_match('~' . $pattern['pattern'] . '~u', ''), $pattern['label']);
            }
        }
    }
}
