<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\TurkishPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionTurkishTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldReplacesSoftGWithG(): void
    {
        $this->assertSame(TurkishPhoneticFolder::fold('Erdoğan'), TurkishPhoneticFolder::fold('Erdogan'));
    }

    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'tur');

        $result = $reviewer->validateFullName('Aldo', 'Muzson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['domuz'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'tur');

        foreach ([['Mehmet', 'Yılmaz'], ['Ayşe', 'Kaya'], ['Ahmet', 'Demir']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
