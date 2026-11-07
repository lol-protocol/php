<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\Parser\PhonePattern;

class PhonePatternTest extends TestCase
{
    /** @return array<string, array{string|null, string}> */
    public static function phones(): array
    {
        return [
            'EE. UU., cualquier idioma' => [null, '555-123-4567'],
            'centralita con nombre' => ['de', 'BUtterfield 8-4521'],
            'Francia' => ['fr', '01 42 68 53 00'],
            'Francia con puntos' => ['fr', '01.42.68.53.00'],
            'Francia internacional' => ['fr', '+33 1 42 68 53 00'],
            'Alemania' => ['de', '030 123456'],
            'Alemania, prefijo entre paréntesis' => ['de', '(089) 654321'],
            'Alemania, barra' => ['de', '0711/12345'],
            'Alemania, extensión' => ['de', '030 12345-67'],
            'Italia' => ['it', '06 1234567'],
            'Italia, barra' => ['it', '02/12345678'],
            'Portugal 2-3-4' => ['pt', '21 342 0000'],
            'Portugal 3-3-3' => ['pt', '213 420 000'],
            'España 2-3-2-2' => ['es', '91 234 56 78'],
            'España 3-3-3' => ['es', '912 345 678'],
            'Chile 1-3-3' => ['es', '2-345-678'],
            'Chile 1-3-4' => ['es', '2-555-1212'],
            'código de área entre paréntesis' => ['es', '(2) 345 6789'],
        ];
    }

    #[DataProvider('phones')]
    public function testRecognizesTheWholeNumber(?string $language, string $phone): void
    {
        $this->assertTrue(PhonePattern::isOnlyAPhoneNumber($phone, $language), $phone);
    }

    /** @return array<string, array{string, string}> */
    public static function notPhones(): array
    {
        return [
            'código postal' => ['es', '28013 Madrid'],
            'calle con número' => ['es', 'Calle Mayor 12'],
            'año' => ['de', 'seit 1925'],
            'número de calle y piso' => ['fr', '12 rue de la Paix 3e étage'],
            'calle alemana' => ['de', 'Hauptstraße 5'],
            'calle portuguesa' => ['pt', 'Rua Augusta 100'],
            'calle italiana con número' => ['it', 'Via Roma 10'],
            'kilómetro de ruta' => ['es', 'Ruta 5 km 123'],
        ];
    }

    #[DataProvider('notPhones')]
    public function testFindsNoPhoneWhereThereIsNone(string $language, string $text): void
    {
        $this->assertSame(0, preg_match(PhonePattern::regexFor($language), $text, $m), $text . ' → ' . ($m[0] ?? ''));
    }

    /** @return array<string, array{string, string, string}> */
    public static function phonesInsideALine(): array
    {
        return [
            'no se lleva el número de la calle (EE. UU. en español)' => ['es', 'GARCÍA LÓPEZ Juan Calle Mayor 12 555-123-4567', '555-123-4567'],
            'no se lleva el número de la calle (Alemania)' => ['de', 'MÜLLER Hans Hauptstraße 5 030 123456', '030 123456'],
            'no se lleva el número de la calle (Francia)' => ['fr', 'DUPONT Marie 12 rue de la Paix 01 42 68 53 00', '01 42 68 53 00'],
            'no se lleva el número de la calle (Portugal)' => ['pt', 'SILVA João Rua Augusta 100 21 342 0000', '21 342 0000'],
            'no se lleva el número de la calle (Chile)' => ['es', 'ROJAS Carmen Calle Larga 5 2-345-678', '2-345-678'],
        ];
    }

    #[DataProvider('phonesInsideALine')]
    public function testFindsOnlyThePhoneInALine(string $language, string $line, string $phone): void
    {
        $this->assertSame(1, preg_match(PhonePattern::regexFor($language), $line, $m));
        $this->assertSame($phone, $m[0]);
    }

    public function testEnglishAndUnknownLanguagesKeepTheBasePattern(): void
    {
        $this->assertSame(PhonePattern::getValidatedRegex(), PhonePattern::regexFor('en'));
        $this->assertSame(PhonePattern::getValidatedRegex(), PhonePattern::regexFor(null));
        $this->assertFalse(PhonePattern::isOnlyAPhoneNumber('01 42 68 53 00'), 'sin idioma, solo formatos de EE. UU.');
    }

    public function testFindRepairsMisreadDigitsOnlyWhenNeeded(): void
    {
        $regex = PhonePattern::regexFor('en');

        $this->assertSame('555-123-4567', PhonePattern::find('KING, Mary 555-l23-4567', $regex));
        $this->assertSame('555-123-4567', PhonePattern::find('555-123-4567', $regex));
        $this->assertNull(PhonePattern::find('KING, Mary', $regex));
    }

    /** @return array<string, array{string, string}> */
    public static function ocrLines(): array
    {
        return [
            'ele por uno' => ['555-l23-4567', '555-123-4567'],
            'i mayúscula y o' => ['555-I23-456O', '555-123-4560'],
            'dentro de una línea' => ['KING, Mary 555-l23-4567', 'KING, Mary 555-123-4567'],
            'palabra' => ['Oil Lane', 'Oil Lane'],
            'nombre' => ['OLIVER, Ollie', 'OLIVER, Ollie'],
            'central con nombre' => ['BUtterfield 8-4521', 'BUtterfield 8-4521'],
            'número de calle corto' => ['1O Main Street', '1O Main Street'],
            'demasiadas letras' => ['5l5-lll-4567', '5l5-lll-4567'],
            'sin nada que arreglar' => ['555-123-4567', '555-123-4567'],
        ];
    }

    #[DataProvider('ocrLines')]
    public function testRepairOcrDigits(string $line, string $expected): void
    {
        $this->assertSame($expected, PhonePattern::repairOcrDigits($line));
    }

    /** @return array<string, array{string, string, string|null}> */
    public static function linesWithAPhone(): array
    {
        return [
            'con puntos guía' => ['SMITH, John ........ 555-123-4567', '555-123-4567', 'SMITH, John'],
            'con dos puntos' => ['ACME Corp: 555-123-4567', '555-123-4567', 'ACME Corp'],
            'solo el teléfono' => ['555-123-4567', '555-123-4567', null],
            'con extensión' => ['555-123-4567 ext. 12', '555-123-4567', null],
            'teléfono que no está' => ['SMITH, John', '555-123-4567', null],
        ];
    }

    #[DataProvider('linesWithAPhone')]
    public function testNameBesidePhone(string $line, string $phone, ?string $expected): void
    {
        $this->assertSame($expected, PhonePattern::nameBesidePhone($line, $phone));
    }
}
