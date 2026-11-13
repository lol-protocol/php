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
            'un santo no es una persona' => ['Farmacia San José', false],
            'santa' => ['Hotel Santa María', false],
            'saint, en francés' => ['Église Saint Pierre', false],
            'st con punto' => ['Hospital St. Joseph', false],
            'sao, en portugués' => ['Padaria São João', false],
            'un santo y además una persona' => ['Panadería San Juan Pérez', true],
            'el nombre antes del santo cuenta' => ['Juan San José', true],
            'palabra que también es nombre, sin santo' => ['Florería Rosa', true],
            'san solo' => ['Cafetería San', false],
            'solo apellido, ahora se oculta' => ['Panadería Pérez', true],
            'apellido alemán' => ['Bäckerei Müller', true],
            'apellido inglés' => ['Smith & Sons', true],
            'apellido italiano' => ['Pasticceria Rossi', true],
            'apellido que es una palabra común, se deja' => ['Café Torres', false],
            'costa' => ['Hotel Costa del Sol', false],
            'apellido después de un santo' => ['Hotel San Martín', false],
            'sin nombre de pila' => ['Restaurante Siglo 21', false],
            'sigla' => ['ACME Corp', false],
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
