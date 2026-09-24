<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\SurnameKeys;

class SurnameKeysTest extends TestCase
{
    public function testSoundexMatchesNearSpellings(): void
    {
        $this->assertSame(SurnameKeys::soundex('Smith'), SurnameKeys::soundex('Smyth'));
        $this->assertSame(SurnameKeys::soundex('Schmidt'), SurnameKeys::soundex('Schmitt'));
        $this->assertSame(SurnameKeys::soundex('Müller'), SurnameKeys::soundex('Mueller'));
        $this->assertNotSame(SurnameKeys::soundex('Smith'), SurnameKeys::soundex('Jones'));
    }

    public function testSoundexIgnoresAccentsAndCase(): void
    {
        $this->assertSame(SurnameKeys::soundex('GONZÁLEZ'), SurnameKeys::soundex('gonzales'));
    }

    public function testSoundexOfEmptyIsNull(): void
    {
        $this->assertNull(SurnameKeys::soundex(null));
        $this->assertNull(SurnameKeys::soundex(''));
    }

    public function testSpanishKeyMatchesInitialBV(): void
    {
        $this->assertNotSame(SurnameKeys::soundex('Valdez'), SurnameKeys::soundex('Baldez'));
        $this->assertSame(SurnameKeys::languageKey('Valdez', 'es'), SurnameKeys::languageKey('Baldez', 'es'));
        $this->assertSame(SurnameKeys::languageKey('Hernández', 'es'), SurnameKeys::languageKey('Ernandez', 'es'));
    }

    public function testLanguageKeyIsNullWithoutSupportedLanguage(): void
    {
        $this->assertNull(SurnameKeys::languageKey('Smith', null));
        $this->assertNull(SurnameKeys::languageKey('Smith', 'en'));
    }

    public function testRootSkipsParticles(): void
    {
        $this->assertSame('Cruz', SurnameKeys::root('de la Cruz'));
        $this->assertSame('García', SurnameKeys::root('García López'));
        $this->assertSame('Rohe', SurnameKeys::root('van der Rohe'));
        $this->assertNull(SurnameKeys::root('de la'));
    }
}
