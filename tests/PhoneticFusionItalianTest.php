<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\ItalianPhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionItalianTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldDoesNotMergeBAndV(): void
    {
        $this->assertNotSame(
            ItalianPhoneticFolder::fold('vino'),
            ItalianPhoneticFolder::fold('bino')
        );
    }

    public function testFoldDoesNotMergeSAndZ(): void
    {
        // El italiano no tiene la misma confusión s/z que español y portugués.
        $this->assertNotSame(
            ItalianPhoneticFolder::fold('mezzo'),
            ItalianPhoneticFolder::fold('messo')
        );
    }

    public function testFoldOnlyStripsAccentsSpacesAndHyphens(): void
    {
        $this->assertSame('perche', ItalianPhoneticFolder::fold('perché'));
        $this->assertSame('rossibianchi', ItalianPhoneticFolder::fold('Rossi-Bianchi'));
    }

    /** Ejemplo construido para probar el mecanismo, no un chiste documentado. */
    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ita');

        $result = $reviewer->validateFullName('Roma', 'Gro');

        $this->assertFalse($result->isValid());
        $this->assertSame(['magro'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ita');

        foreach ([['Giulia', 'Ferrari'], ['Marco', 'Rossi'], ['Luciano', 'Pavarotti']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
