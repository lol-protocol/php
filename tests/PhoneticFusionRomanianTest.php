<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\RomanianPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionRomanianTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldUnifiesAWithI(): void
    {
        $this->assertSame(RomanianPhoneticFolder::fold('câine'), RomanianPhoneticFolder::fold('cîine'));
    }

    public function testFoldNormalizesCommaAndCedillaVariants(): void
    {
        $this->assertSame(RomanianPhoneticFolder::fold('ştefan'), RomanianPhoneticFolder::fold('ștefan'));
        $this->assertSame(RomanianPhoneticFolder::fold('rațiune'), RomanianPhoneticFolder::fold('raţiune'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ron');

        $result = $reviewer->validateFullName('Alp', 'Orcson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['porc'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ron');

        foreach ([['Ion', 'Popescu'], ['Maria', 'Ionescu'], ['Andrei', 'Stan']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
