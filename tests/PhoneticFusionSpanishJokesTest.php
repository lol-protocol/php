<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

/**
 * "Elba Gina" ("el vagina"), "Felipe Lotas" ("Feli-pelotas"), "Susana Oria"
 * ("su zanahoria"): nombre y apellido, ninguno ofensivo por separado, que
 * al leerse seguidos y sin pausa componen otra palabra — los ejemplos
 * reales que motivaron esta función.
 */
class PhoneticFusionSpanishJokesTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
    }

    public function testElbaGinaFusesIntoVagina(): void
    {
        $result = $this->reviewer->validateFullName('Elba', 'Gina');

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasOnlyPhoneticDetections());
        $this->assertSame(['vagina'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testFelipeLotasFusesIntoPelotas(): void
    {
        $result = $this->reviewer->validateFullName('Felipe', 'Lotas');

        $this->assertFalse($result->isValid());
        $this->assertSame(['pelotas'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testSusanaOriaFusesIntoZanahoria(): void
    {
        $result = $this->reviewer->validateFullName('Susana', 'Oria');

        $this->assertFalse($result->isValid());
        $this->assertSame('low', $result->getSeverity());
        $this->assertSame(['zanahoria'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testPacoCojesMatchesCogesAsSpellingVariant(): void
    {
        // No es fusión (todo el término cae dentro del apellido): es la
        // variante "Cojes" de la entrada del diccionario "coges".
        $result = $this->reviewer->validateFullName('Paco', 'Cojes');

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getPhoneticVariantTerms());
        $this->assertEmpty($result->getPhoneticFusionTerms());
    }
}
