<?php

namespace Tests;

use DefamatoryContentReview\CzechPhoneticFolder;
use DefamatoryContentReview\DanishPhoneticFolder;
use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\DutchPhoneticFolder;
use DefamatoryContentReview\FinnishPhoneticFolder;
use DefamatoryContentReview\HungarianPhoneticFolder;
use DefamatoryContentReview\IndonesianPhoneticFolder;
use DefamatoryContentReview\NorwegianPhoneticFolder;
use DefamatoryContentReview\PolishPhoneticFolder;
use DefamatoryContentReview\RomanianPhoneticFolder;
use DefamatoryContentReview\SlovakPhoneticFolder;
use DefamatoryContentReview\SwedishPhoneticFolder;
use DefamatoryContentReview\TurkishPhoneticFolder;
use PHPUnit\Framework\TestCase;

/**
 * Los doce idiomas en script latino que se suman a los cinco de
 * `PhoneticFusionMultiLanguageTest` — separados en su propio archivo por
 * volumen, no por criterio distinto. Cada uno pliega sólo las ambigüedades
 * que un hablante nativo reconocería como reales y sistemáticas (casi
 * siempre las que se enseñan explícitamente en la escuela porque el oído no
 * las resuelve solo): "y"/"i" en checo y eslovaco, "å"→"aa" en las lenguas
 * escandinavas, "ó"→"u" en polaco, "ei"/"ij" en neerlandés, "â"/"î" en
 * rumano, la reforma ortográfica de 1972 en indonesio, la sustitución sin
 * teclado turco de ı/ş/ç/ö/ü/ğ. Ninguna regla aquí es una aproximación
 * inventada — cada docblock de folder explica la fuente real de la
 * ambigüedad que pliega, y qué deja fuera a propósito.
 */
class PhoneticFusionLatinScriptExtendedTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    // -----------------------------------------------------------------
    // Checo
    // -----------------------------------------------------------------

    public function testCzechFoldUnifiesYWithI(): void
    {
        $this->assertSame(CzechPhoneticFolder::fold('mýlit'), CzechPhoneticFolder::fold('mílit'));
    }

    public function testCzechFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ces');

        $result = $reviewer->validateFullName('Alp', 'Raseson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['prase'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testCzechCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ces');

        foreach ([['Jan', 'Novák'], ['Petr', 'Svoboda'], ['Josef', 'Dvořák']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Eslovaco
    // -----------------------------------------------------------------

    public function testSlovakFoldUnifiesYWithI(): void
    {
        $this->assertSame(SlovakPhoneticFolder::fold('bystrý'), SlovakPhoneticFolder::fold('bistrí'));
    }

    public function testSlovakFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'slk');

        $result = $reviewer->validateFullName('Alp', 'Rasason');

        $this->assertFalse($result->isValid());
        $this->assertSame(['prasa'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testSlovakCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'slk');

        foreach ([['Ján', 'Kováč'], ['Peter', 'Horváth'], ['Mária', 'Nováková']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Danés
    // -----------------------------------------------------------------

    public function testDanishFoldExpandsAaToItsHistoricalSpelling(): void
    {
        $this->assertSame(DanishPhoneticFolder::fold('Kierkegård'), DanishPhoneticFolder::fold('Kierkegaard'));
    }

    public function testDanishFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'dan');

        $result = $reviewer->validateFullName('Als', 'Vinson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['svin'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testDanishCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'dan');

        foreach ([['Anders', 'Hansen'], ['Mette', 'Jensen'], ['Lars', 'Nielsen']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Noruego
    // -----------------------------------------------------------------

    public function testNorwegianFoldExpandsAaToItsHistoricalSpelling(): void
    {
        $this->assertSame(NorwegianPhoneticFolder::fold('Åberg'), NorwegianPhoneticFolder::fold('Aaberg'));
    }

    public function testNorwegianFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'nor');

        $result = $reviewer->validateFullName('Als', 'Vinson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['svin'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testNorwegianCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'nor');

        foreach ([['Ole', 'Hansen'], ['Kari', 'Olsen'], ['Erik', 'Johansen']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Sueco
    // -----------------------------------------------------------------

    public function testSwedishFoldExpandsAaToItsHistoricalSpelling(): void
    {
        $this->assertSame(SwedishPhoneticFolder::fold('Åberg'), SwedishPhoneticFolder::fold('Aaberg'));
    }

    public function testSwedishFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'swe');

        $result = $reviewer->validateFullName('Als', 'Vinson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['svin'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testSwedishCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'swe');

        foreach ([['Anders', 'Andersson'], ['Karin', 'Johansson'], ['Lars', 'Eriksson']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Finlandés
    // -----------------------------------------------------------------

    public function testFinnishFoldDropsUmlautToBaseVowel(): void
    {
        $this->assertSame(FinnishPhoneticFolder::fold('Väinö'), FinnishPhoneticFolder::fold('Vaino'));
    }

    public function testFinnishFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'fin');

        $result = $reviewer->validateFullName('Als', 'Ikason');

        $this->assertFalse($result->isValid());
        $this->assertSame(['sika'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testFinnishCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'fin');

        foreach ([['Matti', 'Virtanen'], ['Anna', 'Korhonen'], ['Juha', 'Mäkinen']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Húngaro
    // -----------------------------------------------------------------

    public function testHungarianFoldUnifiesLyWithJ(): void
    {
        $this->assertSame(HungarianPhoneticFolder::fold('Bajor'), HungarianPhoneticFolder::fold('Balyor'));
    }

    public function testHungarianFoldDropsLengthOnDoubleAcuteVowels(): void
    {
        $this->assertSame(HungarianPhoneticFolder::fold('győr'), HungarianPhoneticFolder::fold('györ'));
    }

    public function testHungarianFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'hun');

        $result = $reviewer->validateFullName('Aldi', 'Sznóson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['disznó'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testHungarianCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'hun');

        foreach ([['János', 'Kovács'], ['Katalin', 'Nagy'], ['Béla', 'Szabó']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Indonesio
    // -----------------------------------------------------------------

    public function testIndonesianFoldUnifiesOldVanOphuijsenOeWithU(): void
    {
        $this->assertSame(IndonesianPhoneticFolder::fold('Soekarno'), IndonesianPhoneticFolder::fold('Sukarno'));
    }

    public function testIndonesianFoldUnifiesOldJWithY(): void
    {
        $this->assertSame(IndonesianPhoneticFolder::fold('Jusuf'), IndonesianPhoneticFolder::fold('Yusuf'));
    }

    public function testIndonesianFoldUnifiesOldChWithKh(): void
    {
        $this->assertSame(IndonesianPhoneticFolder::fold('Achmad'), IndonesianPhoneticFolder::fold('Akhmad'));
    }

    public function testIndonesianFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ind');

        $result = $reviewer->validateFullName('Alb', 'Abison');

        $this->assertFalse($result->isValid());
        $this->assertSame(['babi'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testIndonesianCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ind');

        foreach ([['Budi', 'Santoso'], ['Siti', 'Rahayu'], ['Agus', 'Wijaya']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Turco
    // -----------------------------------------------------------------

    public function testTurkishFoldReplacesSoftGWithG(): void
    {
        $this->assertSame(TurkishPhoneticFolder::fold('Erdoğan'), TurkishPhoneticFolder::fold('Erdogan'));
    }

    public function testTurkishFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'tur');

        $result = $reviewer->validateFullName('Aldo', 'Muzson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['domuz'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testTurkishCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'tur');

        foreach ([['Mehmet', 'Yılmaz'], ['Ayşe', 'Kaya'], ['Ahmet', 'Demir']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Polaco
    // -----------------------------------------------------------------

    public function testPolishFoldUnifiesOWithU(): void
    {
        $this->assertSame(PolishPhoneticFolder::fold('mój'), PolishPhoneticFolder::fold('muj'));
    }

    public function testPolishFoldUnifiesRzWithZ(): void
    {
        $this->assertSame(PolishPhoneticFolder::fold('morze'), PolishPhoneticFolder::fold('może'));
    }

    public function testPolishFoldUnifiesChWithH(): void
    {
        $this->assertSame(PolishPhoneticFolder::fold('chleb'), PolishPhoneticFolder::fold('hleb'));
    }

    public function testPolishFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'pol');

        $result = $reviewer->validateFullName('Als', 'Winiason');

        $this->assertFalse($result->isValid());
        $this->assertSame(['świnia'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testPolishCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'pol');

        foreach ([['Jan', 'Kowalski'], ['Anna', 'Nowak'], ['Piotr', 'Wiśniewski']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Neerlandés
    // -----------------------------------------------------------------

    public function testDutchFoldUnifiesEiWithIj(): void
    {
        $this->assertSame(DutchPhoneticFolder::fold('wij'), DutchPhoneticFolder::fold('wei'));
    }

    public function testDutchFoldUnifiesAuWithOu(): void
    {
        $this->assertSame(DutchPhoneticFolder::fold('koud'), DutchPhoneticFolder::fold('kaud'));
    }

    public function testDutchFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'nld');

        $result = $reviewer->validateFullName('Alz', 'Weinson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['zwijn'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testDutchCommonNamesAreNotFlagged(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'nld');

        foreach ([['Jan', 'de Vries'], ['Anna', 'Bakker'], ['Willem', 'Jansen']] as [$first, $last]) {
            $this->assertTrue(
                $reviewer->validateFullName($first, $last)->isValid(),
                "'{$first} {$last}' no debería marcarse."
            );
        }
    }

    // -----------------------------------------------------------------
    // Rumano
    // -----------------------------------------------------------------

    public function testRomanianFoldUnifiesAWithI(): void
    {
        $this->assertSame(RomanianPhoneticFolder::fold('câine'), RomanianPhoneticFolder::fold('cîine'));
    }

    public function testRomanianFoldNormalizesCommaAndCedillaVariants(): void
    {
        $this->assertSame(RomanianPhoneticFolder::fold('ştefan'), RomanianPhoneticFolder::fold('ștefan'));
        $this->assertSame(RomanianPhoneticFolder::fold('rațiune'), RomanianPhoneticFolder::fold('raţiune'));
    }

    public function testRomanianFusionCrossesBoundary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ron');

        $result = $reviewer->validateFullName('Alp', 'Orcson');

        $this->assertFalse($result->isValid());
        $this->assertSame(['porc'], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    public function testRomanianCommonNamesAreNotFlagged(): void
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
