<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\NorwegianPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionNorwegianTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldExpandsAaToItsHistoricalSpelling(): void
    {
        $this->assertSame(NorwegianPhoneticFolder::fold('Åberg'), NorwegianPhoneticFolder::fold('Aaberg'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'nor');

        $result = $reviewer->validateFullName('Alsv', 'In');

        $this->assertFalse($result->isValid());
        $this->assertSame(['svin'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'nor');

        foreach ([['Ole', 'Hansen'], ['Kari', 'Olsen'], ['Erik', 'Johansen']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
