<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\LanguageRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LanguageCodeResolutionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;
    private LanguageRegistry $registry;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
        $this->registry = $this->reviewer->languages()->registry();
    }

    public function testResolvesThreeLetterCode(): void
    {
        $this->assertSame('spa', $this->registry->resolve('spa'));
        $this->assertSame('deu', $this->registry->resolve('deu'));
    }

    public function testAcceptsTwoLetterCodeAsAlias(): void
    {
        $this->assertSame('spa', $this->registry->resolve('es'));
        $this->assertSame('deu', $this->registry->resolve('de'));
        $this->assertSame('yue', $this->registry->resolve('zh'));
        $this->assertSame('ell', $this->registry->resolve('el'));
    }

    public function testResolveIsCaseInsensitive(): void
    {
        $this->assertSame('spa', $this->registry->resolve('SPA'));
        $this->assertSame('por', $this->registry->resolve('PT'));
    }

    public function testUnknownCodeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->registry->resolve('xyz');
    }

    public function testAllThirtyThreeLanguagesAreRegistered(): void
    {
        $this->assertCount(33, $this->registry->getCodes());
    }

    public function testEveryRegisteredLanguageHasADictionaryFile(): void
    {
        foreach ($this->registry->getCodes() as $code) {
            $this->assertFileExists(
                self::CONFIG_DIR . "/languages/{$code}.php",
                "Falta el diccionario de '{$code}'."
            );
        }
    }

    public function testEveryDictionaryDeclaresItsOwnCode(): void
    {
        foreach ($this->registry->getCodes() as $code) {
            $this->assertSame($code, $this->reviewer->languages()->wordList($code)->getLanguage());
        }
    }
}
