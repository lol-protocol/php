<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Las respuestas de review/urgente/spa-genero.csv, aplicadas al español. */
class SpanishReviewAnswersTest extends TestCase
{
    /** @return array<string,array{string,string}> línea, decisión */
    public static function lines(): array
    {
        return [
            'vaca dirigida' => ['eres una vaca', 'reject'],
            'vaca animal' => ['la vaca da leche', 'approve'],
            'marrana dirigida' => ['eres una marrana', 'reject'],
            'gorda dirigida' => ['eres una gorda', 'review'],
            'gorda descriptiva' => ['mi abuela está gorda', 'approve'],
            'cojones exclamación' => ['¡cojones!', 'approve'],
            'pendeja' => ['eres una pendeja', 'reject'],
            'lagarta' => ['eres una lagarta', 'review'],
            'pájara' => ['menuda pájara', 'approve'],
            'putita' => ['eres una putita', 'reject'],
            'cerdita' => ['mi cerdita', 'approve'],
        ];
    }

    #[DataProvider('lines')]
    public function testDecision(string $line, string $decision): void
    {
        $this->assertSame($decision, ChatLineReviewer::create(__DIR__ . '/../../config', 'spa')->review($line)->getDecision(), $line);
    }
}
