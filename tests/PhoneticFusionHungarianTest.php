<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\HungarianPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionHungarianTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldUnifiesLyWithJ(): void
    {
        $this->assertSame(HungarianPhoneticFolder::fold('Bajor'), HungarianPhoneticFolder::fold('Balyor'));
    }

    public function testFoldDropsLengthOnDoubleAcuteVowels(): void
    {
        $this->assertSame(HungarianPhoneticFolder::fold('győr'), HungarianPhoneticFolder::fold('györ'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'hun');

        $result = $reviewer->validateFullName('Aldi', 'Sznóson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['disznó'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'hun');

        foreach ([['János', 'Kovács'], ['Katalin', 'Nagy'], ['Béla', 'Szabó']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
