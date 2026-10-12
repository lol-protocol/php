<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Términos dentro de una racha de letras sueltas que junta varias palabras (ver SpacedRunTerms). */
class SpacedRunTermsTest extends TestCase
{
    /** @return array<string,array{string,string,string,string}> idioma, línea, decisión, línea censurada */
    public static function lines(): array
    {
        return [
            'saludo + insulto' => ['spa', 'h o l a p u t a', 'review', 'h o l a *******'],
            'insulto repetido' => ['spa', 'p u t a p u t a', 'review', '******* *******'],
            'insulto + palabra' => ['spa', 'p u t a m a d r e', 'review', '******* m a d r e'],
            'palabra deletreada que lo contiene: revisión, nunca bloqueo' => ['spa', 'c o m p u t a d o r a', 'review', 'c o m ******* d o r a'],
            'la racha entera sigue bloqueando' => ['spa', 'eres una p u t a', 'reject', 'eres una *******'],
            'con conectora delante sigue bloqueando' => ['spa', 'a p u t a', 'reject', 'a *******'],
            'siglas' => ['spa', 'la O N U dice', 'approve', 'la O N U dice'],
            'término de menos de 4 letras' => ['spa', 'e r e s u n a n o', 'approve', 'e r e s u n a n o'],
            'inglés' => ['eng', 'y o u i d i o t', 'review', 'y o u *********'],
            'tema de chat (sexual)' => ['spa', 'q u i e r o p o r n o', 'review', 'q u i e r o *********'],
        ];
    }

    #[DataProvider('lines')]
    public function testFindsTermsInsideLongerRunsButOnlySendsThemToReview(string $language, string $line, string $decision, string $censored): void
    {
        $result = ChatLineReviewer::create(__DIR__ . '/../../config', $language)->review($line);

        $this->assertSame($decision, $result->getDecision(), $line);
        $this->assertSame($censored, $result->censored(), $line);
    }

    public function testMarksWhereTheMatchCameFrom(): void
    {
        $matches = ChatLineReviewer::create(__DIR__ . '/../../config', 'spa')->review('h o l a p u t a')->getMatches();

        $this->assertSame(['inside'], array_column($matches, 'spaced'));
        $this->assertSame('medium', $matches[0]['severity']);
    }
}
