<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\FrenchPhoneticFolder;
use DefamatoryContentReview\GermanPhoneticFolder;
use DefamatoryContentReview\ItalianPhoneticFolder;
use DefamatoryContentReview\PhoneticFolder;
use DefamatoryContentReview\PhoneticFolderRegistry;
use DefamatoryContentReview\PortuguesePhoneticFolder;
use PHPUnit\Framework\TestCase;

/**
 * La fusión fonética ("Elba Gina" -> "el vagina") no es un fenómeno
 * exclusivamente español. Este archivo cubre las cinco fonéticas
 * "clásicas" (español, portugués, italiano, francés, alemán), cada una con
 * sus propias reglas de plegado (b/v y s/z se confunden en español y
 * portugués pero no en italiano; la "h" es muda en español, portugués y
 * francés, pero endurece la c/g en italiano y no se toca en absoluto en
 * alemán). Los otros doce idiomas en script latino (checo, eslovaco, danés,
 * noruego, sueco, finlandés, húngaro, indonesio, turco, polaco, neerlandés,
 * rumano) están en `PhoneticFusionLatinScriptExtendedTest`, separados por
 * volumen, no por criterio distinto: el resguardo — exigir que la
 * coincidencia cruce la frontera entre nombre y apellido — es el mismo en
 * los diecisiete.
 */
class PhoneticFusionMultiLanguageTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    // -----------------------------------------------------------------
    // Registro: qué idiomas tienen plegado fonético
    // -----------------------------------------------------------------

    private const SUPPORTED = [
        'spa', 'por', 'ita', 'fra', 'deu',
        'ces', 'slk', 'dan', 'nor', 'swe', 'fin', 'hun', 'ind', 'tur', 'pol', 'nld', 'ron',
    ];

    public function testRegistryListsExactlyTheSupportedLanguages(): void
    {
        $this->assertSame(self::SUPPORTED, PhoneticFolderRegistry::supportedLanguages());
    }

    public function testUnsupportedLanguageFoldsToUnchangedText(): void
    {
        $this->assertSame('bastard', PhoneticFolderRegistry::fold('eng', 'bastard'));
    }

    public function testWordListReflectsRegistrySupport(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');

        foreach (self::SUPPORTED as $code) {
            $this->assertTrue($reviewer->getWordList($code)->supportsPhoneticFolding(), $code);
        }

        // vie: script latino pero excluido a propósito (tono fonémico).
        // rus, jpn: script no latino, el mecanismo no aplica sin romanización.
        foreach (['eng', 'vie', 'rus', 'jpn'] as $code) {
            $this->assertFalse($reviewer->getWordList($code)->supportsPhoneticFolding(), $code);
        }
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
    // Francés
    // -----------------------------------------------------------------

    public function testFrenchFoldUnifiesCedillaAndSoftCWithS(): void
    {
        $this->assertSame(
            FrenchPhoneticFolder::fold('garçon'),
            FrenchPhoneticFolder::fold('garson')
        );
    }

    public function testFrenchFoldUnifiesPhWithF(): void
    {
        $this->assertSame(
            FrenchPhoneticFolder::fold('phare'),
            FrenchPhoneticFolder::fold('fare')
        );
    }

    public function testFrenchFoldProtectsChDigraph(): void
    {
        // "chat" (gato) no debe sonar como "cat" con una c(a) suelta.
        $this->assertNotSame(FrenchPhoneticFolder::fold('chat'), FrenchPhoneticFolder::fold('cat'));
    }

    public function testFrenchFoldDropsSilentH(): void
    {
        $this->assertSame(FrenchPhoneticFolder::fold('homme'), FrenchPhoneticFolder::fold('omme'));
    }

    /** Ejemplo construido para probar el mecanismo, no un chiste documentado. */
    public function testFrenchFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'fra');

        $result = $reviewer->validateFullName('Aubi', 'Termont');

        $this->assertFalse($result->isValid());
        $this->assertSame(['bite'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testFrenchCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'fra');

        foreach ([['Jean', 'Dupont'], ['Marie', 'Martin'], ['Luciano', 'Fernandez']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Alemán
    // -----------------------------------------------------------------

    public function testGermanFoldExpandsUmlautsToTheirAlternateSpelling(): void
    {
        $this->assertSame('mueller', GermanPhoneticFolder::fold('Müller'));
        $this->assertSame('strasse', GermanPhoneticFolder::fold('Straße'));
    }

    public function testGermanFoldUnifiesWWithV(): void
    {
        $this->assertSame(
            GermanPhoneticFolder::fold('Wagen'),
            GermanPhoneticFolder::fold('Vagen')
        );
    }

    public function testGermanFoldDoesNotTouchNativeV(): void
    {
        // "Vater" suena /f/ pero "Vase" suena /v/: sin diccionario de origen
        // no se puede distinguir un caso del otro, así que no se pliega.
        $this->assertSame('vater', GermanPhoneticFolder::fold('Vater'));
    }

    /** Ejemplo construido para probar el mecanismo, no un chiste documentado. */
    public function testGermanFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'deu');

        $result = $reviewer->validateFullName('Konrad', 'Ummerath');

        $this->assertFalse($result->isValid());
        $this->assertSame(['dumm'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testGermanCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'deu');

        $names = [
            ['Hans', 'Müller'], ['Anna', 'Schmidt'], ['Wolfgang', 'Meyer'],
            ['Ingrid', 'Wagner'], ['Mariano', 'Schulz'],
        ];

        foreach ($names as [$first, $last]) {
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
