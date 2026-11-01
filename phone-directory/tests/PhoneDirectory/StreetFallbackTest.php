<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\Parser\StreetFallback;

class StreetFallbackTest extends TestCase
{
    /** @return array<string, array{string, string|null}> */
    public static function lines(): array
    {
        return [
            'número y palabras' => ['9 Wexford Close', '9 Wexford Close'],
            'con tildes y guion, entera' => ['8 Clos Saint-Éloi', '8 Clos Saint-Éloi'],
            'empieza con letra con tilde' => ['14 Ó Briain Close', '14 Ó Briain Close'],
            'número con letra' => ['12B Baker Close', '12B Baker Close'],
            'apóstrofo' => ["3 O'Neill Gardens", "3 O'Neill Gardens"],
            'espacios alrededor' => ['  7 Elm Grove  ', '7 Elm Grove'],
            'número en medio de un nombre' => ['Restaurante 3 Hermanos', null],
            'número al final' => ['Los Aromos 123', null],
            'departamento' => ['Apt 4 Building 7', null],
            'teléfono no reconocido' => ['06 1234567', null],
            'otro número después' => ['12 Oak 4', null],
            'solo un número' => ['12', null],
        ];
    }

    #[DataProvider('lines')]
    public function testNumberFirst(string $line, ?string $expected): void
    {
        $this->assertSame($expected, StreetFallback::numberFirst($line));
    }

    public function testInvalidUtf8IsNotAStreet(): void
    {
        $this->assertNull(StreetFallback::numberFirst("12 Pe\xF1a"));
        $this->assertNull(StreetFallback::numberLast("Pe\xF1a 12"));
    }

    /** @return array<string, array{string, string|null}> */
    public static function numberLastLines(): array
    {
        return [
            'palabras y número' => ['Los Aromos 123', 'Los Aromos 123'],
            'con artículo' => ['Travessa do Carmo 5', 'Travessa do Carmo 5'],
            'con tildes' => ['Paseo Ñuñoa 40', 'Paseo Ñuñoa 40'],
            'número con letra' => ['Vicolo Stretto 3a', 'Vicolo Stretto 3a'],
            'espacios alrededor' => ['  Los Aromos 123  ', 'Los Aromos 123'],
            'número en medio' => ['Restaurante 3 Hermanos', null],
            'número primero' => ['12 Oak', null],
            'teléfono' => ['555-123-4567', null],
            'solo un número' => ['12', null],
            'número demasiado largo' => ['Los Aromos 123456', null],
        ];
    }

    #[DataProvider('numberLastLines')]
    public function testNumberLast(string $line, ?string $expected): void
    {
        $this->assertSame($expected, StreetFallback::numberLast($line));
    }
}
