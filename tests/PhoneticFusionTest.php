<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\PhoneticFolder;
use DefamatoryContentReview\PhoneticFusionDetector;
use DefamatoryContentReview\ValidationResult;
use DefamatoryContentReview\WordList;
use PHPUnit\Framework\TestCase;

/**
 * "Elba Gina" ("el vagina"), "Felipe Lotas" ("Feli-pelotas"), "Susana Oria"
 * ("su zanahoria"): nombre y apellido, ninguno ofensivo por separado, que al
 * leerse seguidos y sin pausa componen otra palabra. Estas pruebas cubren esa
 * fusión fonética — y, con la misma importancia, que el detector NO se
 * disparé con nombres reales y corrientes (Mariano, Luciano...) que sólo
 * comparten letras con la palabra oculta, sin cruzar la frontera entre los
 * dos campos.
 */
class PhoneticFusionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
    }

    // -----------------------------------------------------------------
    // PhoneticFolder
    // -----------------------------------------------------------------

    public function testFoldUnifiesBAndV(): void
    {
        $this->assertSame(PhoneticFolder::fold('vaca'), PhoneticFolder::fold('baca'));
    }

    public function testFoldUnifiesSZAndSoftC(): void
    {
        $this->assertSame(PhoneticFolder::fold('zapato'), PhoneticFolder::fold('sapato'));
        $this->assertSame(PhoneticFolder::fold('cielo'), PhoneticFolder::fold('sielo'));
    }

    public function testFoldUnifiesLlAndY(): void
    {
        $this->assertSame(PhoneticFolder::fold('calle'), PhoneticFolder::fold('caye'));
    }

    public function testFoldDropsSilentH(): void
    {
        $this->assertSame(PhoneticFolder::fold('hola'), PhoneticFolder::fold('ola'));
    }

    public function testFoldPreservesChDigraph(): void
    {
        $this->assertNotSame(PhoneticFolder::fold('chino'), PhoneticFolder::fold('cino'));
    }

    public function testFoldUnifiesJAndSoftG(): void
    {
        $this->assertSame(PhoneticFolder::fold('cojes'), PhoneticFolder::fold('coges'));
    }

    public function testFoldStripsSpacesForFusion(): void
    {
        $this->assertSame(PhoneticFolder::fold('el gato'), PhoneticFolder::fold('elgato'));
    }

    // -----------------------------------------------------------------
    // Ejemplos reales de fusión (los "nombres graciosos con doble sentido")
    // -----------------------------------------------------------------

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

    // -----------------------------------------------------------------
    // El resguardo: nombres reales no deben dispararse
    // -----------------------------------------------------------------

    /**
     * "ano" aparece dentro de "Mariano", "Luciano", "Adriano"... Si el
     * detector no exigiera cruzar la frontera entre nombre y apellido,
     * cualquiera de estos apellidos corrientes dispararía una alerta. La
     * exigencia de cruce es precisamente lo que lo evita.
     */
    public function testCommonNamesContainingRiskyFragmentsAreNotFlagged(): void
    {
        $safeNames = [
            ['Juan', 'Mariano'],
            ['Ana', 'Luciano'],
            ['Pedro', 'Adriano'],
            ['Damián', 'Contreras'],
            ['Cristiano', 'Silva'],
            ['Emiliano', 'Torres'],
            ['Juan', 'Pérez'],
            ['María', 'González'],
        ];

        foreach ($safeNames as [$first, $last]) {
            $result = $this->reviewer->validateFullName($first, $last);

            $this->assertTrue(
                $result->isValid(),
                "'{$first} {$last}' no debería marcarse: el fragmento de riesgo no cruza la frontera."
            );
        }
    }

    public function testFragmentFullyInsideOneFieldDoesNotCountAsFusion(): void
    {
        $wordList = $this->reviewer->getWordList('spa');
        $detector = new PhoneticFusionDetector($wordList);

        // "ano" cae entero dentro de "Mariano" (apellido), no cruza hacia "Juan".
        $this->assertEmpty($detector->detectFusion('Juan', 'Mariano'));
    }

    // -----------------------------------------------------------------
    // La decisión nunca rechaza automáticamente sólo por inferencia fonética
    // -----------------------------------------------------------------

    public function testPhoneticOnlyHighSeverityCapsAtReviewNotReject(): void
    {
        $result = new ValidationResult('Prueba Prueba', true, 'spa');
        $result->addFlaggedTerm([
            'found' => 'bastardo',
            'category' => 'moral',
            'riskType' => 'moral',
            'severity' => 'high',
            'detectionMethod' => 'phonetic_fusion',
        ]);
        $result->setValid(false)->setSeverity('high');

        $this->assertTrue($result->hasOnlyPhoneticDetections());
        $this->assertSame('review', $this->reviewer->decide($result));
    }

    public function testLiteralHighSeverityIsStillRejected(): void
    {
        $result = $this->reviewer->validateFullName('Luis', 'Bastardo');

        $this->assertSame('high', $result->getSeverity());
        $this->assertFalse($result->hasOnlyPhoneticDetections());
        $this->assertSame('reject', $this->reviewer->decide($result));
    }

    public function testMixingLiteralAndPhoneticIsNotPhoneticOnly(): void
    {
        $result = new ValidationResult('x', false, 'spa');
        $result->addFlaggedTerm([
            'found' => 'idiota',
            'category' => 'intelectual',
            'riskType' => 'intelectual',
            'severity' => 'medium',
            'detectionMethod' => 'literal',
        ]);
        $result->addFlaggedTerm([
            'found' => 'vagina',
            'category' => 'ordinario',
            'riskType' => 'ordinario',
            'severity' => 'medium',
            'detectionMethod' => 'phonetic_fusion',
        ]);

        $this->assertFalse($result->hasOnlyPhoneticDetections());
    }

    // -----------------------------------------------------------------
    // No duplica lo que la búsqueda literal ya cubrió
    // -----------------------------------------------------------------

    public function testLiterallyMatchedSurnameIsNotAlsoFlaggedAsVariant(): void
    {
        $result = $this->reviewer->validateFullName('Ana', 'Cerda');

        $this->assertEmpty($result->getPhoneticVariantTerms());
        $this->assertCount(1, $result->getFlaggedTerms());
    }

    // -----------------------------------------------------------------
    // Español únicamente
    // -----------------------------------------------------------------

    public function testFusionDetectionIsSkippedForOtherLanguages(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'eng');

        // Mismos campos que producen una fusión en español: en inglés no se
        // evalúa (PhoneticFolder es específico del español).
        $result = $reviewer->validateFullName('Elba', 'Gina');

        $this->assertTrue($result->isValid());
    }

    public function testEmptyFieldsDoNotCrashFusionDetection(): void
    {
        $result = $this->reviewer->validateFullName('', '');

        $this->assertTrue($result->isValid());
    }

    // -----------------------------------------------------------------
    // WordList: candidatos e índice fonético
    // -----------------------------------------------------------------

    public function testFusionCandidatesRespectMinimumLength(): void
    {
        $wordList = $this->reviewer->getWordList('spa');

        foreach ($wordList->getFusionCandidates(5) as $candidate) {
            $this->assertGreaterThanOrEqual(5, mb_strlen($candidate['phonetic']));
        }
    }

    public function testSearchPhoneticExactFindsSpellingVariant(): void
    {
        $wordList = $this->reviewer->getWordList('spa');

        // "Serda" con s: mismo sonido que "Cerda" (c ante e -> s), otra grafía.
        $this->assertNotNull($wordList->searchPhoneticExact('serda'));
        $this->assertSame(
            $wordList->searchPhoneticExact('serda')['original'],
            $wordList->search('cerda')['original']
        );
    }
}
