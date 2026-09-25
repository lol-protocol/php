<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\SlovakPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionSlovakTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldUnifiesYWithI(): void
    {
        $this->assertSame(SlovakPhoneticFolder::fold('bystrý'), SlovakPhoneticFolder::fold('bistrí'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'slk');

        $result = $reviewer->validateFullName('Alp', 'Rasason');

        $this->assertFalse($result->isValid());
        $this->assertSame(['prasa'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'slk');

        foreach ([['Ján', 'Kováč'], ['Peter', 'Horváth'], ['Mária', 'Nováková']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
