<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\FinnishPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionFinnishTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldDropsUmlautToBaseVowel(): void
    {
        $this->assertSame(FinnishPhoneticFolder::fold('Väinö'), FinnishPhoneticFolder::fold('Vaino'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'fin');

        $result = $reviewer->validateFullName('Als', 'Ikason');

        $this->assertFalse($result->isValid());
        $this->assertSame(['sika'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'fin');

        foreach ([['Matti', 'Virtanen'], ['Anna', 'Korhonen'], ['Juha', 'Mäkinen']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
