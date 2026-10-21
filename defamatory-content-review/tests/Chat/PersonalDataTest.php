<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Un RUT, un correo, un teléfono o una dirección de alguien van a revisión
 * con el tipo `privado`, en cualquier idioma; las fechas y las cifras no.
 */
class PersonalDataTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string,string}> idioma, línea, dato */
    public static function exposed(): array
    {
        return [
            'RUT con puntos' => ['spa', 'su RUT es 12.345.678-5', 'RUT'],
            'RUT sin puntos' => ['spa', 'rut 12345678-5', 'RUT'],
            'RUT con K' => ['spa', '11.111.112-K', 'RUT'],
            'correo' => ['spa', 'escríbele a fulano@gmail.com', 'correo'],
            'teléfono internacional' => ['spa', 'llámalo al +56 9 8765 4321', 'teléfono'],
            'teléfono anunciado' => ['spa', 'fono: 9 8765 4321', 'teléfono'],
            'dirección' => ['spa', 'vive en la calle Maipú 123', 'dirección'],
            'dirección en otra lista de idioma' => ['fra', 'vive en Av. Siempreviva 742', 'dirección'],
            'phone' => ['eng', 'call him at 555-123-4567', 'teléfono'],
            'address' => ['eng', 'she lives at 742 Evergreen Terrace', 'dirección'],
        ];
    }

    #[DataProvider('exposed')]
    public function testPersonalDataGoesToReview(string $language, string $line, string $kind): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line);

        $this->assertSame('review', $result->getDecision(), $line);
        $this->assertContains('dato personal (' . $kind . ')', array_column($result->getMatches(), 'original'));
        $this->assertContains('privado', $result->getContentTypes());
    }

    /** @return array<string,array{string}> */
    public static function ordinaryLines(): array
    {
        return [
            'años de una guerra' => ['la guerra de 1939-1945 cambió todo'],
            'fechas' => ['nació en 1914-1918 y murió el 12-03-1925'],
            'RUT con dígito verificador equivocado' => ['folio 12.345.678-9'],
            'población' => ['tiene 1.234.567 habitantes'],
            'ciudad' => ['vivía en Concepción'],
            'voto' => ['+1 a lo que dices'],
        ];
    }

    #[DataProvider('ordinaryLines')]
    public function testOrdinaryNumbersAreApproved(string $line): void
    {
        $this->assertSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review($line)->getDecision(), $line);
    }

    public function testTheDataIsMaskedInTheCensoredLine(): void
    {
        $censored = ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review('escríbele a fulano@gmail.com')->censored();

        $this->assertStringNotContainsString('fulano@gmail.com', $censored);
    }
}
