<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\PhoneticFusionDetector;
use PHPUnit\Framework\TestCase;

/**
 * El resguardo del detector: sólo cuenta una coincidencia que CRUCE la
 * frontera entre nombre y apellido. "ano" aparece dentro de "Mariano",
 * "Luciano", "Adriano"... Sin esa exigencia, cualquiera de estos apellidos
 * corrientes dispararía una alerta falsa.
 */
class PhoneticFusionBoundaryGuardTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
    }

    public function testCommonNamesContainingRiskyFragmentsAreNotFlagged(): void
    {
        $safeNames = [
            ['Juan', 'Mariano'],
            ['Ana', 'Luciano'],
            ['Pedro', 'Adriano'],
            ['Damián', 'Contreras'],
            ['Cristiano', 'Silva'],
            ['Emiliano', 'Torres'],
            ['Juan', 'Pérez'],
            ['María', 'González'],
        ];

        foreach ($safeNames as [$first, $last]) {
            $result = $this->reviewer->validateFullName($first, $last);

            $this->assertTrue(
                $result->isValid(),
                "'{$first} {$last}' no debería marcarse: el fragmento de riesgo no cruza la frontera."
            );
        }
    }

    public function testFragmentFullyInsideOneFieldDoesNotCountAsFusion(): void
    {
        $wordList = $this->reviewer->languages()->wordList('spa');
        $detector = new PhoneticFusionDetector($wordList);

        // "ano" cae entero dentro de "Mariano" (apellido), no cruza hacia "Juan".
        $this->assertEmpty($detector->detectFusion('Juan', 'Mariano'));
    }

    public function testFusionDetectionIsSkippedForOtherLanguages(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'eng');

        // Mismos campos que producen una fusión en español: en inglés no se
        // evalúa (PhoneticFolder es específico del español).
        $result = $reviewer->validateFullName('Elba', 'Gina');

        $this->assertTrue($result->isValid());
    }

    public function testEmptyFieldsDoNotCrashFusionDetection(): void
    {
        $result = $this->reviewer->validateFullName('', '');

        $this->assertTrue($result->isValid());
    }
}
