<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\Exception\InvalidLanguageException;
use PhoneDirectory\Parser\StreetRules;

class StreetRulesTest extends TestCase
{
    /** @return array<string, array{string, string, bool}> */
    public static function streetWords(): array
    {
        return [
            'palabra de calle' => ['12 Oak Street', 'en', true],
            'abreviatura St con número' => ['12 Oak St', 'en', true],
            'abreviatura St sin número, no al principio' => ['Oak St', 'en', true],
            'Dr. abre un nombre' => ['Dr. SMITH, John', 'en', false],
            'St. abre un nombre' => ['St. Mary Bakery', 'en', false],
            'St. abre la calle, con número' => ['St. Mary Road 12', 'en', true],
            'Dr como calle' => ['Elm Dr', 'en', true],
            'barra española' => ['C/ Mayor 12', 'es', true],
            'sufijo alemán' => ['Hauptstraße 5', 'de', true],
            'palabra que contiene la palabra de calle' => ['Ruthless Ltd', 'fr', false],
            'UTF-8 inválido' => ["Calle Pe\xF1a 3", 'es', false],
        ];
    }

    #[DataProvider('streetWords')]
    public function testHasStreetWord(string $line, string $language, bool $expected): void
    {
        $this->assertSame($expected, StreetRules::hasStreetWord($line, $language));
    }

    public function testStrongTakesANumberFirstStreetButNotAPhone(): void
    {
        $this->assertSame('9 Wexford Close', StreetRules::strong('9 Wexford Close', 'en'));
        $this->assertNull(StreetRules::strong('555-123-4567', 'en'));
        $this->assertNull(StreetRules::strong('Los Aromos 123', 'es'));
    }

    public function testAfterNameDependsOnTheLanguage(): void
    {
        $this->assertSame('Los Aromos 123', StreetRules::afterName('Los Aromos 123', 'es'));
        $this->assertNull(StreetRules::afterName('Los Aromos 123', 'fr'));
        $this->assertSame('Apt 4 Building 7', StreetRules::afterName('Apt 4 Building 7', 'en'));
        $this->assertNull(StreetRules::afterName('Apt 4 Building 7', 'es'));
    }

    public function testStreetWordOffsetSkipsTheFirstTwoWords(): void
    {
        $this->assertSame(strlen('PLAZA ROJAS Juan '), StreetRules::streetWordOffset('PLAZA ROJAS Juan Calle Mayor', 'es'));
        $this->assertSame(strlen('MÜLLER Hans '), StreetRules::streetWordOffset('MÜLLER Hans Hauptstraße', 'de'));
        $this->assertNull(StreetRules::streetWordOffset('GARCÍA Juan', 'es'));
    }

    public function testUnsupportedLanguage(): void
    {
        $this->expectException(InvalidLanguageException::class);
        StreetRules::pattern('nl');
    }
}
