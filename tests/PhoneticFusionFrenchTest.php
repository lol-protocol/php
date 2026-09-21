<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\FrenchPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionFrenchTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldUnifiesCedillaAndSoftCWithS(): void
    {
        $this->assertSame(
            FrenchPhoneticFolder::fold('garçon'),
            FrenchPhoneticFolder::fold('garson')
        );
    }

    public function testFoldUnifiesPhWithF(): void
    {
        $this->assertSame(
            FrenchPhoneticFolder::fold('phare'),
            FrenchPhoneticFolder::fold('fare')
        );
    }

    public function testFoldProtectsChDigraph(): void
    {
        // "chat" (gato) no debe sonar como "cat" con una c(a) suelta.
        $this->assertNotSame(FrenchPhoneticFolder::fold('chat'), FrenchPhoneticFolder::fold('cat'));
    }

    public function testFoldDropsSilentH(): void
    {
        $this->assertSame(FrenchPhoneticFolder::fold('homme'), FrenchPhoneticFolder::fold('omme'));
    }

    /** Ejemplo construido para probar el mecanismo, no un chiste documentado. */
    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'fra');

        $result = $reviewer->validateFullName('Aubi', 'Termont');

        $this->assertFalse($result->isValid());
        $this->assertSame(['bite'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'fra');

        foreach ([['Jean', 'Dupont'], ['Marie', 'Martin'], ['Luciano', 'Fernandez']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
