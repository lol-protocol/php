<?php

namespace Tests;

use DefamatoryContentReview\AccentFolding;
use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\PhoneticFolder;
use DefamatoryContentReview\TurkishPhoneticFolder;
use PHPUnit\Framework\TestCase;

/**
 * El mismo nombre puede escribirse con el acento precompuesto (NFC, "é" =
 * un carácter) o descompuesto (NFD, "é" = "e" + U+0301). Los mapas de
 * acentos —el de AccentFolding y el propio de cada folder fonético— sólo
 * alcanzan la forma precompuesta, así que la descompuesta evadía el filtro
 * entero por los dos caminos, el literal y el fonético. No es un caso de
 * laboratorio: iOS y buena parte de los registros genealógicos heredados
 * emiten NFD.
 *
 * El turco lo sufre sin necesidad de NFD: la minúscula de "İ" es "i" más
 * U+0307 y no existe forma precompuesta a la que normalizar, así que
 * cualquier término turco escrito con "İ" quedaba fuera del índice.
 */
class UnicodeNormalizationEvasionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    /** "Cerda" con el acento como marca combinante. */
    private const NFD_CERDA = "Ce\u{0301}rda";

    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
    }

    public function testDecomposedInputReachesTheSameIndexKey(): void
    {
        $wordList = $this->reviewer->languages()->wordList('spa');

        $this->assertSame($wordList->normalize('Cérda'), $wordList->normalize(self::NFD_CERDA));
        $this->assertNotNull($wordList->search(self::NFD_CERDA));
    }

    public function testDecomposedInputIsDetectedLiterally(): void
    {
        $result = $this->reviewer->validateName(self::NFD_CERDA);

        $this->assertFalse($result->isValid());
        $this->assertSame($this->reviewer->validateName('Cérda')->getSeverity(), $result->getSeverity());
    }

    /** La fusión fonética también se perdía: "Elba Gína" en NFD no plegaba. */
    public function testDecomposedInputIsDetectedPhonetically(): void
    {
        $expected = $this->reviewer->validateFullName('Elba', 'Gina');
        $actual = $this->reviewer->validateFullName('Elba', "Gi\u{0301}na");

        $this->assertSame($expected->getSeverity(), $actual->getSeverity());
        $this->assertSame($this->reviewer->decide($expected), $this->reviewer->decide($actual));
        $this->assertNotEmpty($actual->getPhoneticFusionTerms());
    }

    public function testTurkishDottedCapitalIsFolded(): void
    {
        $turkish = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'tur');

        foreach (['it', 'inek', 'embesil'] as $term) {
            $dotted = str_replace('i', 'İ', $term);
            $expected = $turkish->validateName($term);
            $actual = $turkish->validateName($dotted);

            $this->assertFalse($actual->isValid(), "'{$dotted}' debería detectarse igual que '{$term}'.");
            $this->assertSame($expected->getSeverity(), $actual->getSeverity());
        }
    }

    /** Los folders fonéticos plegan por su cuenta: el paso les toca también. */
    public function testPhoneticFoldersStripCombiningMarks(): void
    {
        $this->assertSame(PhoneticFolder::fold('Susána'), PhoneticFolder::fold("Susa\u{0301}na"));
        $this->assertSame(TurkishPhoneticFolder::fold('itlik'), TurkishPhoneticFolder::fold('İtlik'));
    }

    /**
     * El rango se acota a U+0300–U+036F a propósito. En tailandés, árabe,
     * hebreo o devanagari las marcas sin espaciado distinguen palabras
     * distintas, y quitarlas con \p{Mn} las colapsaría: "ไม่" (no) perdería
     * el tono que lo separa de "ไม" (seda).
     */
    public function testMarksOutsideTheLatinRangeAreKept(): void
    {
        foreach (["ไม่", "مُحَمَّد", "שָׁלוֹם", "हिन्दी"] as $text) {
            $this->assertSame($text, AccentFolding::stripCombiningMarks($text));
        }
    }
}
