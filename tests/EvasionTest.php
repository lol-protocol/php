<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

/**
 * Vías de evasión frente a un filtro literal, y qué se hizo con cada una:
 *
 * - Transliteración numérica ("c3rda", "v4g1na"): cubierta —
 *   WordList::normalize() sustituye los dígitos/símbolos más comunes antes
 *   de comparar, y los folders fonéticos hacen lo mismo antes de plegar, así
 *   que también se detecta combinada con la fusión ("Elb4"+"G1na").
 * - Apellidos compuestos con guion o apóstrofo ("Pérez-García", "O'Brien"):
 *   cubierta — ver PhoneticFusionMultiLanguageTest para el fix del plegado
 *   fonético; aquí se prueba que la ventana de findInText() ya los manejaba
 *   bien en la vía literal.
 * - Variantes por distancia de edición ("Cerrda", "Certa"): deliberadamente
 *   NO implementada. Ver testEditDistanceEvasionIsADeliberateGap() para el
 *   porqué.
 */
class EvasionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
    }

    // -----------------------------------------------------------------
    // Transliteración numérica ("leet")
    // -----------------------------------------------------------------

    public function testNumericSubstitutionIsCaughtLiterally(): void
    {
        $cases = [
            'V4gina' => 'vagina',
            'C3rda' => 'cerda',
            'p3nd3jo' => 'pendejo',
            'B4st4rd0' => 'bastardo',
        ];

        foreach ($cases as $evasive => $expectedOriginal) {
            $result = $this->reviewer->validateName($evasive);

            $this->assertFalse($result->isValid(), "'{$evasive}' debería detectarse.");
            $this->assertSame(
                $expectedOriginal,
                $this->reviewer->getWordList('spa')->search($evasive)['original'],
                "'{$evasive}' debería resolver a '{$expectedOriginal}'."
            );
        }
    }

    public function testNumericSubstitutionCombinesWithFusion(): void
    {
        // La misma evasión, pero repartida a los dos lados de la frontera
        // de fusión en vez de dentro de un único campo.
        $result = $this->reviewer->validateFullName('Elb4', 'G1na');

        $this->assertFalse($result->isValid());
        $this->assertSame(['vagina'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testDigitsInRealNamesDoNotBreakValidation(): void
    {
        // Resguardo: que la sustitución no introduzca ruido en nombres
        // corrientes que no tienen dígitos en absoluto.
        foreach ([['Juan', 'Pérez'], ['María', 'González']] as [$first, $last]) {
            $this->assertTrue($this->reviewer->validateFullName($first, $last)->isValid());
        }
    }

    // -----------------------------------------------------------------
    // Apellidos compuestos — vía literal (la vía fonética se prueba en
    // PhoneticFusionMultiLanguageTest)
    // -----------------------------------------------------------------

    public function testHyphenatedSurnameChecksBothParts(): void
    {
        $result = $this->reviewer->validateFullName('Ana', 'Pérez-Cerda');

        $this->assertFalse($result->isValid());
        $this->assertSame(['Cerda'], array_column($result->getFlaggedTerms(), 'term'));
    }

    public function testCompoundSurnameWithConnectorWordsIsNotDisrupted(): void
    {
        // "de la Cruz", "del Bosque": el conector no es un término del
        // diccionario, no debería interferir con el resto de la comprobación.
        $result = $this->reviewer->validateFullName('Juan', 'de la Cruz');

        $this->assertTrue($result->isValid());
    }

    // -----------------------------------------------------------------
    // Distancia de edición: gap conocido y deliberado
    // -----------------------------------------------------------------

    /**
     * "Cerrda" (con letra duplicada) no coincide con la entrada "cerda". Se
     * podría cerrar colapsando letras dobles en normalize(), pero eso
     * generaría falsos positivos impredecibles a través de ~4.600 palabras en
     * 30 idiomas: p. ej. colapsar "rr" convertiría el apellido real
     * "Serrano" en "Serano", y no hay forma de verificar a mano, entrada por
     * entrada, qué otras colisiones no deseadas produciría en cada idioma.
     * Se documenta como límite deliberado en vez de implementarse a medias.
     */
    public function testEditDistanceEvasionIsADeliberateGap(): void
    {
        $result = $this->reviewer->validateName('Cerrda');

        $this->assertTrue(
            $result->isValid(),
            'Las variantes por distancia de edición no están cubiertas (ver el docblock de este test).'
        );
    }
}
