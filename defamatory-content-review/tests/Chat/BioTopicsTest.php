<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Http\TextMarks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Los temas de biografía (`config/bio-topics/`): lo que alguien dice de SÍ MISMO. Son opcionales y no
 * cambian el modo normal, que a propósito no marca «tengo cáncer» (en un chat contar algo de uno mismo
 * no difama a nadie).
 */
class BioTopicsTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string,string}> idioma, texto, etiqueta */
    public static function selfDisclosure(): array
    {
        return [
            'salud' => ['spa', 'Tengo cáncer', 'dato sensible (salud)'],
            'salud, con vivo con' => ['spa', 'Vivo con diabetes desde niña', 'dato sensible (salud)'],
            'salud, soy' => ['spa', 'Soy bipolar y lo cuento sin miedo', 'dato sensible (salud)'],
            'salud, sobreviviente' => ['spa', 'Sobreviviente de cáncer', 'dato sensible (salud)'],
            'religión' => ['spa', 'Soy evangélica y madre de tres', 'dato sensible (religión)'],
            'religión, practicante' => ['spa', 'Madre de tres, católica practicante', 'dato sensible (religión)'],
            'testigo de Jehová' => ['spa', 'Soy testigo de Jehová', 'dato sensible (religión)'],
            'política' => ['spa', 'Soy comunista y orgullosa', 'dato sensible (opinión política)'],
            'militante' => ['spa', 'Militante del partido socialista', 'dato sensible (opinión política)'],
            'origen' => ['spa', 'Soy mapuche', 'dato sensible (origen étnico)'],
            'de origen' => ['spa', 'Chilena de origen árabe', 'dato sensible (origen étnico)'],
            'orientación' => ['spa', 'Soy gay', 'dato sensible (orientación sexual)'],
            'health' => ['eng', 'I have cancer', 'sensitive data (health)'],
            'health, I\'m' => ['eng', "I'm bipolar", 'sensitive data (health)'],
            'health, survivor' => ['eng', 'Cancer survivor and archivist', 'sensitive data (health)'],
            'religion' => ['eng', 'I am a Muslim', 'sensitive data (religion)'],
            'religion, I\'m' => ['eng', "I'm a practicing catholic", 'sensitive data (religion)'],
            'politics' => ['eng', 'I am a communist', 'sensitive data (political opinion)'],
            'ethnic origin' => ['eng', 'I am indigenous', 'sensitive data (ethnic origin)'],
            'orientation' => ['eng', "I'm gay", 'sensitive data (sexual orientation)'],
        ];
    }

    #[DataProvider('selfDisclosure')]
    public function testBioModeFlagsWhatSomeoneSaysAboutThemselves(string $language, string $text, string $label): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, $language, 'bio-topics')->review($text);

        $this->assertSame('review', $result->getDecision(), $text);
        $this->assertContains($label, array_column($result->getMatches(), 'original'), $text);
    }

    /** El modo normal no cambia: lo dicho de uno mismo sigue aprobándose en un chat o en una nota. */
    #[DataProvider('selfDisclosure')]
    public function testNormalModeStillApprovesWhatSomeoneSaysAboutThemselves(string $language, string $text): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($text);

        $this->assertSame('approve', $result->getDecision(), $text);
    }

    public function testBioModeNeverBlocksItOnlySendsToReview(): void
    {
        foreach (self::selfDisclosure() as [$language, $text]) {
            $result = ChatLineReviewer::create(self::CONFIG_DIR, $language, 'bio-topics')->review($text);

            $this->assertNotSame('reject', $result->getDecision(), $text);
            $this->assertNotContains('high', array_column($result->getMatches(), 'severity'), $text);
        }
    }
}
