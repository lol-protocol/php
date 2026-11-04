<?php

namespace Tests\Normalization;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\Normalization\EvasionCleaner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rellenos que no se ven y no son de formato (Hangul filler, braille en blanco): «pu​ta» con U+3164 en medio se
 * leía como otra palabra y pasaba. Sólo se quitan entre letras latinas: en coreano son letras de verdad.
 */
class FillerEvasionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string}> */
    public static function fillers(): array
    {
        return [
            'hangul filler U+3164' => ["\u{3164}"],
            'hangul choseong filler U+115F' => ["\u{115F}"],
            'hangul jungseong filler U+1160' => ["\u{1160}"],
            'halfwidth hangul filler U+FFA0' => ["\u{FFA0}"],
            'braille en blanco U+2800' => ["\u{2800}"],
        ];
    }

    #[DataProvider('fillers')]
    public function testAFillerInsideALatinWordIsRemoved(string $filler): void
    {
        $this->assertSame('eres una puta', EvasionCleaner::clean("eres una pu{$filler}ta", 'spa'));
    }

    #[DataProvider('fillers')]
    public function testTheChatRejectsTheInsultWithAFillerInside(string $filler): void
    {
        $chat = ChatLineReviewer::create(self::CONFIG_DIR, 'spa');
        $this->assertSame('reject', $chat->review("eres una pu{$filler}ta")->getDecision());
    }

    #[DataProvider('fillers')]
    public function testANameWithAFillerInsideIsNotApproved(string $filler): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
        $this->assertNotSame('approve', $reviewer->decide($reviewer->validateName("Pu{$filler}ta")));
    }

    public function testKoreanTextKeepsItsFillerLetters(): void
    {
        $korean = "한\u{3164}국어";
        $this->assertMatchesRegularExpression('/한[\x{3164}\x{1160}]국어/u', EvasionCleaner::clean($korean, 'kor'));
    }
}
