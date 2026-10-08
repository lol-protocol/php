<?php

namespace Tests;

use DefamatoryContentReview\ChatLineReviewer;
use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Apellidos y nombres frecuentes que coinciden con un insulto, elegidos con datos de
 * frecuencia y no a ojo:
 *
 * - eng: apellidos con 1.000 personas o más en el censo de EE. UU. de 2010
 *   (fivethirtyeight/data, most-common-name/surnames.csv), p. ej. Outlaw 8.365, Dyke 5.005;
 * - spa: apellidos entre los 8.000 más frecuentes de una lista española ordenada por
 *   frecuencia (smashew/NameDatabases, surnames/es.txt), p. ej. Chaparro n.º 924, Rufián n.º 5.357;
 * - más dos nombres de pila conocidos: Mona e India.
 *
 * Por debajo de esos umbrales no se marcan: cada nameCollision baja a revisión también el
 * insulto en un chat, y eso sólo compensa si el apellido es frecuente.
 */
class FrequentSurnameCollisionTest extends TestCase
{
    private const BY_LANGUAGE = [
        'eng' => ['Outlaw', 'Coward', 'Dyke', 'Leech', 'Buzzard', 'Tart', 'Batty', 'Swindler', 'Mule', 'Miser', 'Sinner', 'Square', 'Worm'],
        'spa' => ['Chaparro', 'Mula', 'Zurdo', 'Payo', 'Cansino', 'Borrega', 'Diestro', 'Pardillo', 'Rufián', 'Obeso', 'Carroza', 'Pendón', 'Gandul', 'Orejón', 'Bastardo', 'Mona', 'India'],
    ];

    /** @return array<string,array{string,string}> */
    public static function names(): array
    {
        $cases = [];
        foreach (self::BY_LANGUAGE as $language => $names) {
            foreach ($names as $name) {
                $cases["{$language}: {$name}"] = [$language, $name];
            }
        }

        return $cases;
    }

    #[DataProvider('names')]
    public function testIsDetectedAsANameButNeverRejectedAutomatically(string $language, string $name): void
    {
        $reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', $language);
        $result = $reviewer->validateName($name);

        $this->assertFalse($result->isValid(), "«{$name}» debería seguir detectándose");
        $this->assertTrue($result->hasNameCollision(), "«{$name}» debe llevar nameCollision");
        $this->assertNotSame('reject', $reviewer->decide($result));
    }

    #[DataProvider('names')]
    public function testAGreetingWithTheNameIsNeverBlockedInTheChat(string $language, string $name): void
    {
        $chat = ChatLineReviewer::create(__DIR__ . '/../config', $language);

        $this->assertNotSame('reject', $chat->review("Hola {$name}, ¿cómo estás?")->getDecision());
    }
}
