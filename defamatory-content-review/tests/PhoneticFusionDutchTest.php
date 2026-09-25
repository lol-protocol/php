<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\DutchPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionDutchTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldUnifiesEiWithIj(): void
    {
        $this->assertSame(DutchPhoneticFolder::fold('wij'), DutchPhoneticFolder::fold('wei'));
    }

    public function testFoldUnifiesAuWithOu(): void
    {
        $this->assertSame(DutchPhoneticFolder::fold('koud'), DutchPhoneticFolder::fold('kaud'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'nld');

        $result = $reviewer->validateFullName('Alzw', 'Ein');

        $this->assertFalse($result->isValid());
        $this->assertSame(['zwijn'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'nld');

        foreach ([['Jan', 'de Vries'], ['Anna', 'Bakker'], ['Willem', 'Jansen']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
