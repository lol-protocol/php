<?php

namespace MarcalibrosApp\Tests;

use MarcalibrosApp\LabelPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** El texto de un marcalibro se publica en nuestro dominio: ni insultos, ni acusaciones, ni datos personales. */
final class LabelPolicyTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function refused(): array
    {
        return [
            'insulto' => ['Fulano hijo de puta'],
            'insulto disfrazado' => ['p u t a'],
            'insulto en inglés' => ['you are a bitch'],
            'acusación' => ['Fulano es un estafador'],
            'dato sensible' => ['Fulano tiene sida'],
            'dato personal' => ['RUT 12.345.678-5'],
            'amenaza' => ['te voy a matar'],
        ];
    }

    #[DataProvider('refused')]
    public function testRefusesWhatCouldHurtSomeone(string $label): void
    {
        $this->assertNotNull((new LabelPolicy())->problem($label), $label);
    }

    /** @return array<string, array{?string}> */
    public static function accepted(): array
    {
        return [
            'sin texto' => [null],
            'vacío' => ['  '],
            'título' => ['Cien años de soledad'],
            'dedicatoria' => ['Para mi abuela Rosa'],
            'en inglés' => ['Keep reading'],
            'apellido' => ['Biblioteca Concha y Toro'],
        ];
    }

    #[DataProvider('accepted')]
    public function testAcceptsOrdinaryTexts(?string $label): void
    {
        $this->assertNull((new LabelPolicy())->problem($label), (string) $label);
    }
}
