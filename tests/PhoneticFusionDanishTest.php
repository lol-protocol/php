<?php

namespace Tests;

use DefamatoryContentReview\DanishPhoneticFolder;
use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

class PhoneticFusionDanishTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldExpandsAaToItsHistoricalSpelling(): void
    {
        $this->assertSame(DanishPhoneticFolder::fold('Kierkegård'), DanishPhoneticFolder::fold('Kierkegaard'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'dan');

        $result = $reviewer->validateFullName('Als', 'Vinson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['svin'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'dan');

        foreach ([['Anders', 'Hansen'], ['Mette', 'Jensen'], ['Lars', 'Nielsen']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
