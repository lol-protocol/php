<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

/**
 * Transliteración numérica ("c3rda", "v4g1na"): cubierta — WordList::normalize()
 * sustituye los dígitos/símbolos más comunes antes de comparar, y los
 * folders fonéticos hacen lo mismo antes de plegar, así que también se
 * detecta combinada con la fusión ("Elb4"+"G1na").
 */
class NumericEvasionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
    }

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
                $this->reviewer->languages()->wordList('spa')->search($evasive)['original'],
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
}
