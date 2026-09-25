<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\GermanPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionGermanTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldExpandsUmlautsToTheirAlternateSpelling(): void
    {
        $this->assertSame('mueller', GermanPhoneticFolder::fold('Müller'));
        $this->assertSame('strasse', GermanPhoneticFolder::fold('Straße'));
    }

    public function testFoldUnifiesWWithV(): void
    {
        $this->assertSame(
            GermanPhoneticFolder::fold('Wagen'),
            GermanPhoneticFolder::fold('Vagen')
        );
    }

    public function testFoldDoesNotTouchNativeV(): void
    {
        // "Vater" suena /f/ pero "Vase" suena /v/: sin diccionario de origen
        // no se puede distinguir un caso del otro, así que no se pliega.
        $this->assertSame('vater', GermanPhoneticFolder::fold('Vater'));
    }

    /** Ejemplo construido para probar el mecanismo, no un chiste documentado. */
    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'deu');

        $result = $reviewer->validateFullName('Konrad', 'Ummerath');

        $this->assertFalse($result->isValid());
        $this->assertSame(['dumm'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'deu');

        $names = [
            ['Hans', 'Müller'], ['Anna', 'Schmidt'], ['Wolfgang', 'Meyer'],
            ['Ingrid', 'Wagner'], ['Mariano', 'Schulz'],
        ];

        foreach ($names as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
