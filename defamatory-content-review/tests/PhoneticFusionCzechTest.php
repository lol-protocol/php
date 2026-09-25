<?php

namespace Tests;

use DefamatoryContentReview\CzechPhoneticFolder;
use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

class PhoneticFusionCzechTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldUnifiesYWithI(): void
    {
        $this->assertSame(CzechPhoneticFolder::fold('mýlit'), CzechPhoneticFolder::fold('mílit'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ces');

        $result = $reviewer->validateFullName('Alpr', 'Ase');

        $this->assertFalse($result->isValid());
        $this->assertSame(['prase'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ces');

        foreach ([['Jan', 'Novák'], ['Petr', 'Svoboda'], ['Josef', 'Dvořák']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
