<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Palabras de un país hispanohablante, fusionadas en el español base (ver README, «Palabras de cada país»). */
class RegionalWordsTest extends TestCase
{
    /** @return array<string,array{string,string}> línea, decisión */
    public static function lines(): array
    {
        return [
            'puto dirigido' => ['eres un puto', 'reject'],
            'puto solo' => ['¡puto!', 'reject'],
            'puto intensificador' => ['los putos deberes', 'approve'],
            'joto' => ['eres un joto', 'reject'],
            'trolo' => ['sos un trolo', 'reject'],
            'y pico' => ['llego a las tres y pico', 'approve'],
            'pico acompañado' => ['mándame tu pico, quiero follar', 'reject'],
            'pija = presumida' => ['qué pija es tu prima', 'approve'],
            'coger sin más' => ['quiero coger el bus', 'approve'],
            'polla' => ['chúpame la polla', 'reject'],
        ];
    }

    #[DataProvider('lines')]
    public function testDecision(string $line, string $decision): void
    {
        $this->assertSame($decision, ChatLineReviewer::create(__DIR__ . '/../../config', 'spa')->review($line)->getDecision(), $line);
    }
}
