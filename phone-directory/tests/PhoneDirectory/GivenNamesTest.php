<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\GivenNames;

class GivenNamesTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function businessNames(): array
    {
        return [
            'nombre y apellido' => ['Panadería Juan Pérez', true],
            'con tilde y en mayúsculas' => ['FERRETERÍA JOSÉ', true],
            'francés' => ['Chez Marie', true],
            'alemán con Umlaut' => ['Jürgen Weber GmbH', true],
            'nombre de otra lista' => ['Restaurante Giuseppe', true],
            'santo, a propósito de más' => ['Farmacia San José', true],
            'sin nombre de pila' => ['Restaurante Siglo 21', false],
            'sigla' => ['ACME Corp', false],
            'solo apellido' => ['Panadería Pérez', false],
            'nombre dentro de otra palabra' => ['Juanería Central', false],
            'UTF-8 inválido' => ["Panader\xEDa Juan", false],
        ];
    }

    #[DataProvider('businessNames')]
    public function testAppearsIn(string $name, bool $expected): void
    {
        $this->assertSame($expected, GivenNames::appearsIn($name));
    }
}
