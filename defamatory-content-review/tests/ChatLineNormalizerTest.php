<?php

namespace Tests;

use DefamatoryContentReview\ChatLineNormalizer;
use DefamatoryContentReview\ChatMatches;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChatLineNormalizerTest extends TestCase
{
    public function testJoinsARunOfSingleLetters(): void
    {
        $this->assertSame(['eres una puta ahora', ['puta' => 'p u t a']], ChatLineNormalizer::variants('eres una p u t a ahora')[0]);
    }

    public function testKeepsTheCaseAndTheSpacingTheyWrote(): void
    {
        $this->assertSame(['PUTA', ['PUTA' => 'P  U T   A']], ChatLineNormalizer::variants('P  U T   A')[0]);
    }

    public function testAlsoReadsTheLineWithoutTheConnectorLettersAtTheEnds(): void
    {
        $this->assertSame([
            ['vamos afollar', ['afollar' => 'a f o l l a r']],
            ['vamos a follar', ['follar' => 'f o l l a r']],
        ], ChatLineNormalizer::variants('vamos a f o l l a r'));
        $this->assertContains('puta y mierda', array_column(ChatLineNormalizer::variants('p u t a y mierda'), 0));
        $this->assertContains('y a follar', array_column(ChatLineNormalizer::variants('y a f o l l a r'), 0));
    }

    public function testNeverTrimsBelowThreeLetters(): void
    {
        $this->assertSame([['ass', ['ass' => 'a s s']]], ChatLineNormalizer::variants('a s s'));
    }

    public static function untouchedLines(): array
    {
        return ['dos letras' => ['a b'], 'frase normal' => ['Hola a todos'], 'números' => ['1 2 3 4'], 'vacía' => ['']];
    }

    #[DataProvider('untouchedLines')]
    public function testLeavesOtherLinesAlone(string $line): void
    {
        $this->assertSame([[$line, []]], ChatLineNormalizer::variants($line));
    }

    public function testRestoreGivesFoundTheTextAsWritten(): void
    {
        $matches = [['found' => 'puta'], ['found' => 'hijo de puta']];

        $this->assertSame(['p u t a', 'hijo de p u t a'], array_column(ChatMatches::restore($matches, ['puta' => 'p u t a']), 'found'));
        $this->assertSame($matches, ChatMatches::restore($matches, []));
    }

    public function testMergeCountsWhatBothReadingsSawOnlyOnce(): void
    {
        $a = ['found' => 'puta', 'riskType' => 'genero', 'contentType' => 'difamatorio'];
        $b = ['found' => 'bomba', 'riskType' => 'belico', 'contentType' => 'belico'];

        $this->assertSame([$a, $b], ChatMatches::merge([$a], [$a, $b]));
        $this->assertSame([$a, $a], ChatMatches::merge([$a], [$a, $a]), 'una repetición real sí se cuenta');
    }

    public function testFoldForPatternsLowersFoldsAndSpacesOutPunctuation(): void
    {
        $this->assertSame('matalos  arbol i ', ChatLineNormalizer::foldForPatterns('Mátalos, ÁRBOL 1!'));
    }

    public function testFoldForPatternsKeepsOnePositionPerCharacter(): void
    {
        $line = 'Mañana, ¿qué tal? 😀';

        $this->assertSame(mb_strlen($line), strlen(ChatLineNormalizer::foldForPatterns($line)));
    }
}
