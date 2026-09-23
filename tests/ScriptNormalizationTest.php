<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

/**
 * Variantes ortográficas estándar de scripts no latinos (ver ScriptFolding).
 * Cada caso era una evasión real: la palabra del diccionario, escrita de
 * otra forma igual de habitual, pasaba la búsqueda literal.
 */
class ScriptNormalizationTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    /** @return array<string,array{string,string}> */
    public static function variantProvider(): array
    {
        return [
            'griego en mayúsculas, sin tonos y con sigma medial' => ['ell', 'ΜΑΛΑΚΑΣ'],
            'griego en minúsculas sin tonos' => ['ell', 'μαλακας'],
            'ruso con е en vez de ё' => ['rus', 'козел'],
            'ruso con е en vez de ё (otra palabra)' => ['rus', 'осел'],
            'árabe con kashida' => ['ara', 'مـدمـن'],
            'árabe con harakat' => ['ara', 'مُدْمِن'],
            'árabe con ه en vez de ة' => ['ara', 'غبيه'],
            'árabe con alef sin hamza' => ['ara', 'احمق'],
            'hebreo con niqqud' => ['heb', 'מַמְזֵר'],
        ];
    }

    /** @dataProvider variantProvider */
    public function testStandardSpellingVariantIsDetected(string $language, string $word): void
    {
        $result = DefamatoryContentReviewer::create(self::CONFIG_DIR, $language)->validateName($word);

        $this->assertFalse($result->isValid(), "'{$word}' ({$language}) debería detectarse.");
    }

    /** @return array<string,array{string,string}> */
    public static function realNameProvider(): array
    {
        return [
            'apellido griego en mayúsculas' => ['ell', 'ΟΙΚΟΝΟΜΟΥ'],
            'nombre griego en mayúsculas' => ['ell', 'ΝΙΚΟΣ'],
            'apellido ruso con ё' => ['rus', 'Королёв'],
            'apellido ruso con е' => ['rus', 'Королев'],
            'nombre árabe con ة' => ['ara', 'فاطمة'],
            'nombre árabe con ه' => ['ara', 'فاطمه'],
            'nombre árabe con ى' => ['ara', 'مصطفى'],
            'nombre hebreo con niqqud' => ['heb', 'יִצְחָק'],
        ];
    }

    /** @dataProvider realNameProvider */
    public function testRealNamesStayValidAfterNormalization(string $language, string $name): void
    {
        $result = DefamatoryContentReviewer::create(self::CONFIG_DIR, $language)->validateName($name);

        $this->assertTrue($result->isValid(), "'{$name}' ({$language}) es un nombre real.");
    }

    /** "Umayya" es nombre propio además de "analfabeta": va a revisión, nunca a rechazo. */
    public function testUmayyaIsANameCollisionInEveryVariant(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'ara');

        foreach (['أمية', 'امية', 'اميه'] as $variant) {
            $this->assertSame('review', $reviewer->decide($reviewer->validateName($variant)), $variant);
        }
    }
}
