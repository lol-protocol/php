<?php

namespace Tests;

use Marcalibros\Label;
use Marcalibros\RejectionReason;
use PHPUnit\Framework\Attributes\DataProvider;

final class LabelTest extends ImageTestCase
{
    /** @return array<string, array{?string, ?string}> */
    public static function cleaned(): array
    {
        return [
            'null' => [null, null],
            'vacío' => ['', null],
            'solo espacios' => ["  \t\n ", null],
            'recorta y junta espacios y saltos' => ["  Mi\n  marcalibro\t favorito ", 'Mi marcalibro favorito'],
            'quita ceros de ancho' => ["ab\u{200B}c", 'abc'],
            'quita el cambio de sentido del texto' => ["abc\u{202E}def", 'abcdef'],
            'el byte nulo se vuelve espacio' => ["a\0b", 'a b'],
            'conserva tildes, eñes y emojis' => ['Año nuevo ✨', 'Año nuevo ✨'],
        ];
    }

    #[DataProvider('cleaned')]
    public function testCleansTheText(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, Label::clean($raw));
    }

    public function testTruncatesByCharactersNotBytes(): void
    {
        $clean = Label::clean(str_repeat('ñ', 100));

        $this->assertSame(Label::MAX_LENGTH, mb_strlen((string) $clean));
        $this->assertTrue(mb_check_encoding((string) $clean, 'UTF-8'));
    }

    public function testAcceptsACustomMaximumLength(): void
    {
        $this->assertSame('abcde', Label::clean('abcdefgh', 5));
    }

    public function testRejectsInvalidUtf8(): void
    {
        $this->assertRejected(RejectionReason::InvalidLabel, fn () => Label::clean("\xC3\x28"));
    }

    public function testKeepsAngleBracketsBecauseEscapingIsDoneWhenPrinting(): void
    {
        $this->assertSame('<b>hola</b>', Label::clean('<b>hola</b>'));
    }
}
