<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\SwedishPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionSwedishTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldExpandsAaToItsHistoricalSpelling(): void
    {
        $this->assertSame(SwedishPhoneticFolder::fold('Åberg'), SwedishPhoneticFolder::fold('Aaberg'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'swe');

        $result = $reviewer->validateFullName('Alsv', 'In');

        $this->assertFalse($result->isValid());
        $this->assertSame(['svin'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'swe');

        foreach ([['Anders', 'Andersson'], ['Karin', 'Johansson'], ['Lars', 'Eriksson']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
