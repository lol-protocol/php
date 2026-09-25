<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\FusionSupport;
use PHPUnit\Framework\TestCase;

/**
 * Fusión nombre+apellido en idiomas sin reglas fonéticas (ver
 * FusionSupport): la misma detección que "Elba Gina", sobre la forma
 * literal normalizada.
 */
class LiteralFusionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    /** @return array<string,array{string,string,string}> */
    public static function fusionProvider(): array
    {
        return [
            'ruso' => ['rus', 'Сво', 'Лочь'],
            'ucraniano' => ['ukr', 'Негід', 'Ник'],
            'búlgaro' => ['bul', 'Мер', 'Завец'],
            'griego, en mayúsculas' => ['ell', 'ΜΑΛΑ', 'ΚΑΣ'],
            'hindi' => ['hin', 'कमी', 'ना'],
            'islandés' => ['isl', 'Fáv', 'Iti'],
            'swahili' => ['swa', 'Mji', 'Nga'],
            'tagalo' => ['tgl', 'Tara', 'Ntado'],
        ];
    }

    /** @dataProvider fusionProvider */
    public function testFusionIsDetectedAndSentToReview(string $language, string $first, string $last): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, $language);
        $result = $reviewer->validateFullName($first, $last);

        $this->assertNotEmpty($result->getPhoneticFusionTerms(), "{$first} {$last} ({$language})");
        $this->assertNotSame('reject', $reviewer->decide($result), 'la fusión sola nunca rechaza en automático');
    }

    /** @return array<string,array{string,string,string}> */
    public static function excludedProvider(): array
    {
        return [
            'inglés: "Chris Hitt" no es "shit"' => ['eng', 'Chris', 'Hitt'],
            'árabe: "Mohammed Mansour" no es "adicto"' => ['ara', 'محمد', 'منصور'],
            'hebreo' => ['heb', 'יוסף', 'כהן'],
            'vietnamita' => ['vie', 'Minh', 'Nguyễn'],
            'japonés' => ['jpn', '太郎', '山田'],
        ];
    }

    /** @dataProvider excludedProvider */
    public function testExcludedLanguagesHaveNoFusion(string $language, string $first, string $last): void
    {
        $this->assertFalse(FusionSupport::isSupported($language));

        $result = DefamatoryContentReviewer::create(self::CONFIG_DIR, $language)->validateFullName($first, $last);
        $this->assertEmpty($result->getPhoneticFusionTerms());
    }

    public function testCommonRealNamesAreNotFlagged(): void
    {
        foreach ([['rus', 'Иван', 'Петров'], ['ell', 'ΝΙΚΟΣ', 'ΟΙΚΟΝΟΜΟΥ'], ['tgl', 'Juan', 'Dela Cruz'], ['swa', 'Amina', 'Mwangi']] as [$l, $f, $s]) {
            $this->assertTrue(DefamatoryContentReviewer::create(self::CONFIG_DIR, $l)->validateFullName($f, $s)->isValid(), "{$f} {$s}");
        }
    }

    /** Sin reglas fonéticas, no hay "variante" de un solo campo: duplicaría la búsqueda literal. */
    public function testLiteralFusionLanguagesDoNotDoPhoneticVariants(): void
    {
        $wordList = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'rus')->languages()->wordList('rus');

        $this->assertTrue($wordList->supportsFusion());
        $this->assertFalse($wordList->supportsPhoneticFolding());
        $this->assertNull($wordList->searchPhoneticExact('сволочь'));
    }

    public function testCoverageIsTwentySixOfThirtyThree(): void
    {
        $this->assertCount(26, FusionSupport::supportedLanguages());
    }
}
