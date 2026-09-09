<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\ItalianPhoneticFolder;
use DefamatoryContentReview\PhoneticFolder;
use DefamatoryContentReview\PhoneticFolderRegistry;
use DefamatoryContentReview\PortuguesePhoneticFolder;
use PHPUnit\Framework\TestCase;

/**
 * La fusión fonética ("Elba Gina" -> "el vagina") no es un fenómeno
 * exclusivamente español: se extiende aquí a portugués e italiano, cada uno
 * con sus propias reglas de plegado (b/v y s/z se confunden en español y
 * portugués pero no en italiano; la "h" es muda en español y portugués pero
 * endurece la c/g en italiano). El resguardo — exigir que la coincidencia
 * cruce la frontera entre nombre y apellido — es el mismo en los tres.
 */
class PhoneticFusionMultiLanguageTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    // -----------------------------------------------------------------
    // Registro: qué idiomas tienen plegado fonético
    // -----------------------------------------------------------------

    public function testRegistryListsExactlyTheSupportedLanguages(): void
    {
        $this->assertSame(['spa', 'por', 'ita'], PhoneticFolderRegistry::supportedLanguages());
    }

    public function testUnsupportedLanguageFoldsToUnchangedText(): void
    {
        $this->assertSame('bastard', PhoneticFolderRegistry::fold('eng', 'bastard'));
    }

    public function testWordListReflectsRegistrySupport(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');

        $this->assertTrue($reviewer->getWordList('spa')->supportsPhoneticFolding());
        $this->assertTrue($reviewer->getWordList('por')->supportsPhoneticFolding());
        $this->assertTrue($reviewer->getWordList('ita')->supportsPhoneticFolding());
        $this->assertFalse($reviewer->getWordList('eng')->supportsPhoneticFolding());
        $this->assertFalse($reviewer->getWordList('deu')->supportsPhoneticFolding());
    }

    // -----------------------------------------------------------------
    // Portugués
    // -----------------------------------------------------------------

    public function testPortugueseFoldUnifiesCedillaWithS(): void
    {
        // "ç" siempre suena /s/; su forma plegada debe coincidir con la
        // misma palabra escrita con "s" llano.
        $this->assertSame('casa', PortuguesePhoneticFolder::fold('caça'));
    }

    public function testPortugueseFoldUnifiesZWithS(): void
    {
        // La confusión s/z es real en portugués (sobre todo el brasileño):
        // "cozer" (cocinar) y "coser" (coser) suenan casi igual.
        $this->assertSame(
            PortuguesePhoneticFolder::fold('cozer'),
            PortuguesePhoneticFolder::fold('coser')
        );
    }

    public function testPortugueseFoldDoesNotMergeBAndV(): void
    {
        // A diferencia del español, en portugués b/v son sonidos distintos.
        $this->assertNotSame(
            PortuguesePhoneticFolder::fold('vaca'),
            PortuguesePhoneticFolder::fold('baca')
        );
    }

    public function testPortugueseFoldProtectsLhDigraph(): void
    {
        // Si no se protegiera, "filho" (hijo) perdería la h y colisionaría
        // con "filo" (una palabra distinta).
        $this->assertNotSame(
            PortuguesePhoneticFolder::fold('filho'),
            PortuguesePhoneticFolder::fold('filo')
        );
    }

    /**
     * Ejemplo construido para probar el mecanismo (cruce de frontera), no un
     * chiste documentado como los de la imagen original en español.
     */
    public function testPortugueseFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'por');

        $result = $reviewer->validateFullName('Isabu', 'Rroso');

        $this->assertFalse($result->isValid());
        $this->assertSame(['burro'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testPortugueseCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'por');

        foreach ([['Mariana', 'Silva'], ['João', 'Santos'], ['Mariano', 'Souza']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Italiano
    // -----------------------------------------------------------------

    public function testItalianFoldDoesNotMergeBAndV(): void
    {
        $this->assertNotSame(
            ItalianPhoneticFolder::fold('vino'),
            ItalianPhoneticFolder::fold('bino')
        );
    }

    public function testItalianFoldDoesNotMergeSAndZ(): void
    {
        // El italiano no tiene la misma confusión s/z que español y portugués.
        $this->assertNotSame(
            ItalianPhoneticFolder::fold('mezzo'),
            ItalianPhoneticFolder::fold('messo')
        );
    }

    public function testItalianFoldOnlyStripsAccentsSpacesAndHyphens(): void
    {
        $this->assertSame('perche', ItalianPhoneticFolder::fold('perché'));
        $this->assertSame('rossibianchi', ItalianPhoneticFolder::fold('Rossi-Bianchi'));
    }

    /** Ejemplo construido para probar el mecanismo, no un chiste documentado. */
    public function testItalianFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ita');

        $result = $reviewer->validateFullName('Roma', 'Grossi');

        $this->assertFalse($result->isValid());
        $this->assertSame(['magro'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testItalianCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ita');

        foreach ([['Giulia', 'Ferrari'], ['Marco', 'Rossi'], ['Luciano', 'Pavarotti']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Apellidos compuestos con guion o apóstrofo (bug: se perdían en el
    // plegado, rompiendo el cálculo de la frontera de fusión)
    // -----------------------------------------------------------------

    public function testHyphenIsStrippedNotKeptLiterally(): void
    {
        $this->assertStringNotContainsString('-', PhoneticFolder::fold('Pérez-García'));
        $this->assertStringNotContainsString('-', PortuguesePhoneticFolder::fold('Sousa-Lima'));
        $this->assertStringNotContainsString('-', ItalianPhoneticFolder::fold('Rossi-Bianchi'));
    }

    public function testApostropheIsStrippedNotKeptLiterally(): void
    {
        $this->assertStringNotContainsString("'", PortuguesePhoneticFolder::fold("O'Brien"));
    }

    public function testHyphenatedSurnameStillMatchesLiterally(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');

        $result = $reviewer->validateFullName('Ana', 'Pérez-Cerda');

        $this->assertFalse($result->isValid());
        $this->assertSame(['Cerda'], array_column($result->getFlaggedTerms(), 'term'));
    }
}
