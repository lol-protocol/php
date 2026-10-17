<?php

namespace Tests\Http;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Http\LongText;
use DefamatoryContentReview\Http\ModerationEndpoint;
use PHPUnit\Framework\TestCase;

/**
 * Una biografía de más de 2.000 caracteres se revisa por párrafos: lo que hay en
 * cualquiera de ellos cuenta, y un texto corto se sigue revisando entero.
 */
class LongTextTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';
    private const PARAGRAPH = 'Juan nació en Concepción en 1923, hijo de agricultores. Trabajó treinta años en el ferrocarril. ';

    public function testShortTextIsOnePiece(): void
    {
        $this->assertSame(["pu\nta"], LongText::pieces("pu\nta"));
    }

    public function testParagraphsAndLongSentencesAreSplitUnderTheLimit(): void
    {
        $text = str_repeat(self::PARAGRAPH, 15) . "\n\n" . str_repeat(self::PARAGRAPH, 40) . "\n \n" . str_repeat('palabra ', 600);
        $pieces = LongText::pieces($text);

        $this->assertGreaterThan(3, count($pieces));
        foreach ($pieces as $piece) {
            $this->assertLessThanOrEqual(LongText::PIECE_CHARS, mb_strlen($piece));
        }
        $this->assertSame(preg_replace('/\s+/u', '', $text), preg_replace('/\s+/u', '', implode('', $pieces)), 'no se pierde texto');
    }

    public function testAWordLongerThanTheLimitIsCutAnyway(): void
    {
        $this->assertSame([2000, 1000], array_map('mb_strlen', LongText::pieces(str_repeat('a', 3000))));
    }

    public function testTheEndpointFindsWhatIsInAnyParagraph(): void
    {
        $text = str_repeat(self::PARAGRAPH, 25) . "\n\nSu vecino decía que Juan estafó a su familia.\n\n" . str_repeat(self::PARAGRAPH, 25) . 'Era un hijo de puta.';
        $response = (new ModerationEndpoint(self::CONFIG_DIR))->handle('POST', (string) json_encode(['text' => $text]));

        $this->assertGreaterThan(LongText::PIECE_CHARS, mb_strlen($text));
        $this->assertSame(200, $response['status']);
        $this->assertSame('reject', $response['body']['decision']);
        $this->assertSame(['acusación de delito', 'hijo de puta'], array_column($response['body']['matches'], 'term'));
        $this->assertStringContainsString('Era un ************.', $response['body']['censored']);
    }

    public function testAnOrdinaryBiographyIsApproved(): void
    {
        $response = (new ModerationEndpoint(self::CONFIG_DIR))->handle('POST', (string) json_encode(['text' => str_repeat(self::PARAGRAPH . "\n\n", 60)]));

        $this->assertSame('approve', $response['body']['decision']);
    }

    public function testTooMuchSpelledOutTextGoesToReviewWithoutSearchingInside(): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review(str_repeat('q w e r t y ', 30));

        $this->assertSame('review', $result->getDecision());
        $this->assertContains('texto deletreado', array_column($result->getMatches(), 'original'));
    }
}
