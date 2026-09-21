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

    /**
     * Cualquier término de severidad alta y categoría 'etnico' es, por su
     * propia naturaleza, más propenso a coincidir con un gentilicio o
     * apellido real que el resto de categorías (animal, ordinario...).
     * Esta prueba no exige que todos estén marcados — eso depende de
     * revisión nativa (ver CONTRIBUTING.md) — pero documenta cuántos
     * quedan sin marcar, para que crecer esa lista sin querer no pase
     * desapercibido.
     */
    public function testHighSeverityEthnicTermsWithoutNameCollisionIsTracked(): void
    {
        $languagesDir = self::CONFIG_DIR . '/languages';
        $unflagged = [];

        foreach (glob($languagesDir . '/*.php') as $file) {
            if (basename($file) === 'supported-languages.php') {
                continue;
            }

            $data = require $file;
            foreach ($data['words'] ?? [] as $words) {
                foreach ($words as $word) {
                    if (
                        ($word['riskType'] ?? '') === 'etnico'
                        && ($word['severity'] ?? '') === 'high'
                        && empty($word['nameCollision'])
                    ) {
                        $unflagged[] = basename($file, '.php') . ':' . $word['word'];
                    }
                }
            }
        }

        // Umbral de referencia tomado en el momento de escribir esta prueba
        // (tras corregir oláh, tót, negro, negrão, polak y szwab). Si sube,
        // alguien agregó una entrada nueva de alto riesgo étnico sin evaluar
        // si también es un gentilicio o apellido real — no es un error en
        // sí, pero merece una revisión deliberada antes de subir el número
        // aquí.
        //
        // Subido a 162 al añadir yue.php (cantonés): 黑鬼, 死鬼佬, 棒子, 阿差,
        // 賓妹, 大陸妹, 北姑 son jerga despectiva/étnica, no apellidos ni
        // gentilicios documentados — se revisaron y ninguno requiere
        // nameCollision.
        //
        // Subido a 167 al añadir formas femeninas reales en spa.php: chola,
        // naca, panchita, charnega, maketa. Ninguna es apellido español
        // documentado (a diferencia de "moro"/"negro", ya protegidos).
        //
        // Subido a 170 al añadir formas femeninas reales en fra/ita/por.php:
        // youpine, zingara, crioula. Ninguna es apellido documentado.
        //
        // Subido a 172 al añadir formas femeninas reales en deu.php:
        // Zigeunerin, Negerin. Ninguna es apellido documentado.
        //
        // Subido a 173 al añadir formas femeninas reales en ron.php:
        // țigancă. No es apellido documentado.
        $this->assertLessThanOrEqual(
            173,
            count($unflagged),
            'Crecieron los términos étnicos de severidad alta sin nameCollision revisado: ' .
                implode(', ', array_slice($unflagged, 0, 10)) . '...'
        );
    }
}
