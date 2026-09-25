<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\IndonesianPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionIndonesianTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldUnifiesOldVanOphuijsenOeWithU(): void
    {
        $this->assertSame(IndonesianPhoneticFolder::fold('Soekarno'), IndonesianPhoneticFolder::fold('Sukarno'));
    }

    public function testFoldUnifiesOldJWithY(): void
    {
        $this->assertSame(IndonesianPhoneticFolder::fold('Jusuf'), IndonesianPhoneticFolder::fold('Yusuf'));
    }

    public function testFoldUnifiesOldChWithKh(): void
    {
        $this->assertSame(IndonesianPhoneticFolder::fold('Achmad'), IndonesianPhoneticFolder::fold('Akhmad'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ind');

        $result = $reviewer->validateFullName('Alba', 'Bi');

        $this->assertFalse($result->isValid());
        $this->assertSame(['babi'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ind');

        foreach ([['Budi', 'Santoso'], ['Siti', 'Rahayu'], ['Agus', 'Wijaya']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
