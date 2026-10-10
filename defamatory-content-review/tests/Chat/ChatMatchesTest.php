<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatMatches;
use PHPUnit\Framework\TestCase;

class ChatMatchesTest extends TestCase
{
    private function match(string $found, string $severity = 'high', array $extra = []): array
    {
        return ['found' => $found, 'riskType' => 'genero', 'contentType' => 'difamatorio', 'severity' => $severity] + $extra;
    }

    public function testRestoreGivesFoundTheTextAsWritten(): void
    {
        $matches = [['found' => 'puta'], ['found' => 'hijo de puta']];

        $this->assertSame(['p u t a', 'hijo de p u t a'], array_column(ChatMatches::restore($matches, ['puta' => 'p u t a']), 'found'));
        $this->assertSame($matches, ChatMatches::restore($matches, []));
    }

    public function testMergeCountsWhatBothReadingsSawOnlyOnce(): void
    {
        $a = $this->match('puta');
        $b = $this->match('bomba');

        $this->assertSame([$a, $b], ChatMatches::merge([$a], [$a, $b]));
        $this->assertSame([$a, $a], ChatMatches::merge([$a], [$a, $a]), 'una repetición real sí se cuenta');
    }

    public function testOnlyWhatNeededDoubleLettersReducedGoesToReview(): void
    {
        $doubled = $this->match('puuta', 'high', ['repeat' => 'doubled']);
        $elongated = $this->match('puuuta', 'high', ['repeat' => 'elongated']);
        $exact = $this->match('puta');
        $low = $this->match('guerra', 'low', ['repeat' => 'doubled']);

        $out = ChatMatches::downgradeDoubled([$doubled, $elongated, $exact, $low]);

        $this->assertSame(['medium', 'high', 'high', 'low'], array_column($out, 'severity'));
        $this->assertSame('doubled', $out[0]['repeat']);
    }
}
