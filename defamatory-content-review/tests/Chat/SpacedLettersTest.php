<?php

namespace Tests\Chat;

use DefamatoryContentReview\SpacedLetters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SpacedLettersTest extends TestCase
{
    public function testJoinsARunOfSingleLetters(): void
    {
        $this->assertSame(['eres una puta ahora', ['puta' => 'p u t a']], SpacedLetters::variants('eres una p u t a ahora')[0]);
    }

    public function testKeepsTheCaseAndTheSpacingTheyWrote(): void
    {
        $this->assertSame(['PUTA', ['PUTA' => 'P  U T   A']], SpacedLetters::variants('P  U T   A')[0]);
    }

    public function testAlsoReadsTheLineWithoutTheConnectorLettersAtTheEnds(): void
    {
        $this->assertSame([
            ['vamos afollar', ['afollar' => 'a f o l l a r']],
            ['vamos a follar', ['follar' => 'f o l l a r']],
        ], SpacedLetters::variants('vamos a f o l l a r'));
        $this->assertContains('puta y mierda', array_column(SpacedLetters::variants('p u t a y mierda'), 0));
        $this->assertContains('y a follar', array_column(SpacedLetters::variants('y a f o l l a r'), 0));
    }

    public function testNeverTrimsBelowThreeLetters(): void
    {
        $this->assertSame([['ass', ['ass' => 'a s s']]], SpacedLetters::variants('a s s'));
    }

    public static function untouchedLines(): array
    {
        return ['dos letras' => ['a b'], 'frase normal' => ['Hola a todos'], 'números' => ['1 2 3 4'], 'vacía' => ['']];
    }

    #[DataProvider('untouchedLines')]
    public function testLeavesOtherLinesAlone(string $line): void
    {
        $this->assertSame([[$line, []]], SpacedLetters::variants($line));
    }
}
