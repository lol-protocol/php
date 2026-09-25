<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\PortuguesePhoneticFolder;
use PHPUnit\Framework\TestCase;

class PhoneticFusionPortugueseTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    public function testFoldUnifiesCedillaWithS(): void
    {
        // "ç" siempre suena /s/; su forma plegada debe coincidir con la
        // misma palabra escrita con "s" llano.
        $this->assertSame('casa', PortuguesePhoneticFolder::fold('caça'));
    }

    public function testFoldUnifiesZWithS(): void
    {
        // La confusión s/z es real en portugués (sobre todo el brasileño):
        // "cozer" (cocinar) y "coser" (coser) suenan casi igual.
        $this->assertSame(
            PortuguesePhoneticFolder::fold('cozer'),
            PortuguesePhoneticFolder::fold('coser')
        );
    }

    public function testFoldDoesNotMergeBAndV(): void
    {
        // A diferencia del español, en portugués b/v son sonidos distintos.
        $this->assertNotSame(
            PortuguesePhoneticFolder::fold('vaca'),
            PortuguesePhoneticFolder::fold('baca')
        );
    }

    public function testFoldProtectsLhDigraph(): void
    {
        // Si no se protegiera, "filho" (hijo) perdería la h y colisionaría
        // con "filo" (una palabra distinta).
        $this->assertNotSame(
            PortuguesePhoneticFolder::fold('filho'),
            PortuguesePhoneticFolder::fold('filo')
        );
    }

    /** Ejemplo construido para probar el mecanismo (cruce de frontera), no un chiste documentado. */
    public function testFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'por');

        $result = $reviewer->validateFullName('Isabu', 'Rro');

        $this->assertFalse($result->isValid());
        $this->assertSame(['burro'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'por');

        foreach ([['Mariana', 'Silva'], ['João', 'Santos'], ['Mariano', 'Souza']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }
}
