<?php

namespace Tests;

use DefamatoryContentReview\ChatLineResult;
use DefamatoryContentReview\ChatLineReviewer;
use PHPUnit\Framework\TestCase;

class ChatLineResultTest extends TestCase
{
    private function censor(string $line, string $found, string $severity = 'high'): string
    {
        $match = ['found' => $found, 'riskType' => 'ordinario', 'severity' => $severity, 'contentType' => 'difamatorio'];

        return (new ChatLineResult($line, [$match], 'reject'))->censored();
    }

    public function testMasksWholeWordsOnly(): void
    {
        $this->assertSame('idiotas y un ******', $this->censor('idiotas y un idiota', 'idiota'));
    }

    public function testIgnoresCaseEvenOnAccentedCapitals(): void
    {
        $this->assertSame('ERES UN ******', $this->censor('ERES UN CABRÓN', 'cabrón'));
    }

    public function testToleratesAnotherSeparatorBetweenTheWordsOfAPhrase(): void
    {
        $this->assertSame('************ y ya', $this->censor('hijo-de-puta y ya', 'hijo de puta'));
    }

    public function testLeavesLowSeverityTermsVisible(): void
    {
        $this->assertSame('mi abuelo en la guerra', $this->censor('mi abuelo en la guerra', 'guerra', 'low'));
    }

    public function testDoesNotChokeOnAFoundThatIsOnlySeparators(): void
    {
        $this->assertSame('hola', $this->censor('hola', ' - '));
    }

    public function testMasksSpacedLettersAsWrittenAndAThreatAsAWhole(): void
    {
        $chat = ChatLineReviewer::create(__DIR__ . '/../config', 'spa');

        $this->assertSame('Eres una *******', $chat->review('Eres una p u t a')->censored());
        $this->assertSame('vamos a ***********', $chat->review('vamos a f o l l a r')->censored());
        $this->assertSame('Mañana **************', $chat->review('Mañana te voy a matar')->censored());
    }
}
