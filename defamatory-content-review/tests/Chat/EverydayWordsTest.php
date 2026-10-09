<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\EverydayWords;
use DefamatoryContentReview\Normalization\AccentFolding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EverydayWordsTest extends TestCase
{
    private const EVERYDAY = ['moc' => true, 'sık' => true, 'ver-me' => true];

    public function testMasksTheWholeWordKeepingThePositions(): void
    {
        $this->assertSame('    děkuji', EverydayWords::mask('moc děkuji', self::EVERYDAY));
        $this->assertSame(mb_strlen('Sık sık gelirim'), mb_strlen(EverydayWords::mask('Sık sık gelirim', self::EVERYDAY)));
        $this->assertSame('vem        amanhã', EverydayWords::mask('vem ver-me amanhã', self::EVERYDAY));
    }

    public function testIgnoresCaseButNotTheAccentsOrPartsOfWords(): void
    {
        $this->assertSame('   ', EverydayWords::mask('MOC', self::EVERYDAY));
        $this->assertSame('moč', EverydayWords::mask('moč', self::EVERYDAY), 'la palabra con su letra propia sigue siendo el insulto');
        $this->assertSame('mocný', EverydayWords::mask('mocný', self::EVERYDAY), 'sólo palabras enteras');
        $this->assertSame('m0c', EverydayWords::mask('m0c', self::EVERYDAY), 'una evasión no es la palabra cotidiana');
    }

    public function testAnEmptyListLeavesTheTextAlone(): void
    {
        $this->assertSame('moc děkuji', EverydayWords::mask('moc děkuji', []));
    }

    public static function ownLetters(): array
    {
        return [
            'danés ø' => ['dan', 'høre', 'hore'], 'danés å' => ['dan', 'når', 'nar'], 'noruego æ' => ['nor', 'kæft', 'kaeft'],
            'sueco ö' => ['swe', 'höra', 'hora'], 'sueco ä' => ['swe', 'rätta', 'ratta'], 'finés ä' => ['fin', 'täi', 'tai'],
            'vietnamita, tono' => ['vie', 'dái', 'dai'], 'vietnamita, đ' => ['vie', 'đéo', 'deo'],
        ];
    }

    #[DataProvider('ownLetters')]
    public function testLettersOfTheirOwnAreNotFoldedInTheirLanguage(string $language, string $word, string $folded): void
    {
        $this->assertSame($word, AccentFolding::fold($word, $language));
        $this->assertSame($folded, AccentFolding::fold($word), 'en los demás idiomas se siguen plegando');
    }
}
