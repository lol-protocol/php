<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\ItalianPhoneticFolder;
use DefamatoryContentReview\PhoneticFolderRegistry;
use DefamatoryContentReview\PortuguesePhoneticFolder;
use DefamatoryContentReview\SpanishPhoneticFolder;
use PHPUnit\Framework\TestCase;

/**
 * Lo que no es específico de un idioma: qué idiomas tiene registrados
 * PhoneticFolderRegistry, y que los folders manejan bien guiones/apóstrofos
 * en apellidos compuestos (bug real: se perdían en el plegado, rompiendo el
 * cálculo de la frontera de fusión). Cada idioma tiene su propio archivo
 * `PhoneticFusion<Idioma>Test.php`.
 */
class PhoneticFusionRegistryTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

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
            $this->assertTrue($reviewer->languages()->wordList($code)->supportsPhoneticFolding(), $code);
        }

        // eng: script latino, pero sin grafía alternativa real que plegar
        // (ver PhoneticFolderRegistry). vie: script latino pero excluido a
        // propósito (tono fonémico). rus, jpn: script no latino, el
        // mecanismo no aplica sin romanización.
        foreach (['eng', 'vie', 'rus', 'jpn'] as $code) {
            $this->assertFalse($reviewer->languages()->wordList($code)->supportsPhoneticFolding(), $code);
        }
    }

    public function testHyphenIsStrippedNotKeptLiterally(): void
    {
        $this->assertStringNotContainsString('-', SpanishPhoneticFolder::fold('Pérez-García'));
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
