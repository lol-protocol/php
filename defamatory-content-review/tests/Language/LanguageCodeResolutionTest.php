<?php

namespace Tests\Language;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\Language\LanguageRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LanguageCodeResolutionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

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

    public function testTwoLetterCodesAreNotAccepted(): void
    {
        foreach (['es', 'de', 'zh', 'el'] as $code) {
            $this->assertFalse($this->registry->isSupported($code), $code);
        }
        $this->expectException(InvalidArgumentException::class);
        $this->registry->resolve('es');
    }

    public function testHeaderTagsMapTwoLetterToThreeLetter(): void
    {
        $this->assertSame('spa', $this->registry->fromHeaderTag('es'));
        $this->assertSame('deu', $this->registry->fromHeaderTag('DE'));
        $this->assertSame('deu', $this->registry->fromHeaderTag('deu'));
        $this->assertNull($this->registry->fromHeaderTag('xx'));
        $this->assertNull($this->registry->fromHeaderTag('zh'), 'zh es el chino en general: el cantonés es yue');
        $this->assertSame('yue', $this->registry->fromHeaderTag('yue'));
    }

    public function testResolveIsCaseInsensitive(): void
    {
        $this->assertSame('spa', $this->registry->resolve('SPA'));
        $this->assertSame('por', $this->registry->resolve('POR'));
    }

    public function testUnknownCodeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->registry->resolve('xyz');
    }

    public function testRegisteredLanguagesAreExactlyTheDictionaryFiles(): void
    {
        $files = array_map(static fn(string $f): string => basename($f, '.php'), glob(self::CONFIG_DIR . '/languages/*.php') ?: []);
        $codes = $this->registry->getCodes();
        sort($files);
        sort($codes);

        $this->assertSame($files, $codes);
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
