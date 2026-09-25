<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\PolishPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionPolishTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldUnifiesOWithU(): void
    {
        $this->assertSame(PolishPhoneticFolder::fold('mój'), PolishPhoneticFolder::fold('muj'));
    }

    public function testFoldUnifiesRzWithZ(): void
    {
        $this->assertSame(PolishPhoneticFolder::fold('morze'), PolishPhoneticFolder::fold('może'));
    }

    public function testFoldUnifiesChWithH(): void
    {
        $this->assertSame(PolishPhoneticFolder::fold('chleb'), PolishPhoneticFolder::fold('hleb'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'pol');

        $result = $reviewer->validateFullName('Alsw', 'Inia');

        $this->assertFalse($result->isValid());
        $this->assertSame(['świnia'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'pol');

        foreach ([['Jan', 'Kowalski'], ['Anna', 'Nowak'], ['Piotr', 'Wiśniewski']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
