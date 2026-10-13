<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Chilenismos: los insultos se marcan; «weón» como trato de confianza, no. */
class ChileanVocabularyTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string}> línea, decisión */
    public static function lines(): array
    {
        return [
            'conchetumare' => ['conchetumare', 'reject'],
            'frase de 4 palabras' => ['concha de tu madre', 'reject'],
            'weón culiao' => ['weón culiao', 'review'],
            'eres un weón' => ['eres un weón', 'review'],
            'aweonao' => ['qué aweonao', 'review'],
            'hola weón' => ['hola weón, ¿cómo estai?', 'approve'],
            'che boludo' => ['che boludo, vení', 'approve'],
            'ctm como exclamación' => ['ctm se me cayó el café', 'approve'],
        ];
    }

    #[DataProvider('lines')]
    public function testDecision(string $line, string $decision): void
    {
        $this->assertSame($decision, ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review($line)->getDecision(), $line);
    }
}
