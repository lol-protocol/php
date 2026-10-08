<?php

namespace Tests;

use DefamatoryContentReview\ChatLineResult;
use DefamatoryContentReview\ChatLineReviewer;
use PHPUnit\Framework\TestCase;

/** Letras repetidas en el chat: «puuuuta» (3 o más: nunca legítimas) frente a «puuta» (2: pueden serlo). */
class ChatRepeatedLettersTest extends TestCase
{
    private static function review(string $line, string $language = 'spa'): ChatLineResult
    {
        return ChatLineReviewer::create(__DIR__ . '/../config', $language)->review($line);
    }

    public function testThreeOrMoreEqualLettersKeepTheFullSeverity(): void
    {
        $this->assertSame('reject', self::review('puuuuta')->getDecision());
        $this->assertSame('reject', self::review('pusssy', 'eng')->getDecision(), 'la doble legítima de «pussy» sobrevive');
    }

    public function testTwoEqualLettersGoToReviewBecauseTheyCouldBeASurname(): void
    {
        $this->assertSame('review', self::review('puuta')->getDecision());
        $this->assertSame('review', self::review('biitch', 'eng')->getDecision());
    }

    public function testTheDoubleLettersOfTheInsultItselfAreKept(): void
    {
        $result = self::review('vamos a follarr');

        $this->assertSame('review', $result->getDecision());
        $this->assertTrue($result->hasContentType('sexual'));
    }

    public function testTheMatchKeepsTheTextAsWrittenAndSaysHowItWasRead(): void
    {
        $match = self::review('Eres una puuuuta')->getMatches()[0];

        $this->assertSame('puuuuta', $match['found']);
        $this->assertSame('elongated', $match['repeat']);
        $this->assertSame('doubled', self::review('puuta')->getMatches()[0]['repeat']);
    }

    public function testCensorsTheRepeatedSpelling(): void
    {
        $this->assertSame('Eres una ******* madre', self::review('Eres una puuuuta madre')->censored());
    }

    public function testThreatsAcceptRepeatedLettersWithFullSeverity(): void
    {
        $this->assertSame('reject', self::review('te voy a mataaaar')->getDecision());
        $this->assertSame('reject', self::review('tee voy a mataar')->getDecision());
        $this->assertSame('reject', self::review("i'll kiiiill you", 'eng')->getDecision());
    }

    public function testSpacedAndRepeatedLettersTogether(): void
    {
        $this->assertSame('reject', self::review('p u u u t a')->getDecision());
    }

    public function testWordsAndSurnamesWithALegitimateDoubleLetterAreNotTouched(): void
    {
        foreach ([['vivimos en la calle', 'spa'], ['el morro', 'spa'], ['Mr Pratt', 'eng'], ['Hi Jaap', 'eng'], ['looser jeans', 'eng']] as [$line, $language]) {
            $this->assertSame('approve', self::review($line, $language)->getDecision(), $line);
        }
    }

    public function testOtherLanguagesDoNotReadRepeatedLetters(): void
    {
        $this->assertSame('approve', self::review('mamma e nonno', 'ita')->getDecision());
    }
}
