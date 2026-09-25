<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

/**
 * Apellidos reales que en algún momento se rechazaron en automático por
 * severidad alta sin `nameCollision => true`. Encontrado con un barrido de
 * ~6.800 combinaciones reales sobre los 17 idiomas con fusión fonética:
 * "oláh"/"tót" (húngaro), "negro" (italiano), "negrão" (portugués),
 * "polak" (francés) y "szwab" (polaco) son, a la vez, insultos étnicos
 * documentados y apellidos reales. `decide()` nunca debe devolver 'reject'
 * para un apellido real: esta prueba fija esos casos como regresión.
 */
class NameCollisionRegressionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    /** @return array<string,array{string,string,string}> */
    public static function realSurnamesProvider(): array
    {
        return [
            'húngaro: Oláh (apellido romaní húngaro real y frecuente)' => ['hun', 'Béla', 'Oláh'],
            'húngaro: Tót (apellido húngaro documentado)' => ['hun', 'Béla', 'Tót'],
            'italiano: Negro (apellido italiano real, frecuente en Piemonte)' => ['ita', 'Mario', 'Negro'],
            'portugués: Negrão (apellido portugués/brasileño real)' => ['por', 'João', 'Negrão'],
            'francés: Polak (apellido real, checo/polaco/diáspora judía)' => ['fra', 'Oliver', 'Polak'],
            'polaco: Szwab (apellido polaco real y documentado)' => ['pol', 'Jan', 'Szwab'],
            'español: Cerda (apellido real, ya protegido)' => ['spa', 'Zoila', 'Cerda'],
            'español: Moro (apellido real, ya protegido)' => ['spa', 'Juan', 'Moro'],
            'portugués: Pinto (apellido real, ya protegido)' => ['por', 'João', 'Pinto'],
        ];
    }

    /** @dataProvider realSurnamesProvider */
    public function testRealSurnameNeverAutoRejects(string $lang, string $first, string $last): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, $lang);
        $result = $reviewer->validateFullName($first, $last);

        $this->assertNotSame(
            'reject',
            $reviewer->decide($result),
            "'{$first} {$last}' es un apellido real: nunca debería rechazarse en automático."
        );
    }
}
