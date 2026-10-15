<?php

namespace Tests\Review;

use DefamatoryContentReview\Review\ConfigEditor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** La clave de una lista va tal cual al archivo y a una expresión regular: tiene que ser un nombre. */
class ListEditorTest extends TestCase
{
    private const FILE = "<?php\nreturn [\n    'everyday' => ['uno', 'dos'],\n    'words' => [\n    ],\n];\n";

    /** @return array<string,array{string}> */
    public static function notKeys(): array
    {
        return [
            'con comilla' => ["everyday'"],
            'cierra y abre' => ["x'] , 'y"],
            'metacaracteres' => ['.*'],
            'alternancia' => ['uno|everyday'],
            'con espacio' => ['every day'],
            'vacía' => [''],
            'empieza con dígito' => ['1x'],
        ];
    }

    #[DataProvider('notKeys')]
    public function testAKeyThatIsNotAPlainNameIsRefusedAndNeverWritten(string $key): void
    {
        $this->assertNull(ConfigEditor::editList(self::FILE, $key, 'tres', true));
        $this->assertNull(ConfigEditor::editList(self::FILE, $key, 'uno', false));
    }

    public function testAPlainNameStillWorks(): void
    {
        $this->assertStringContainsString("'everyday' => ['uno', 'dos', 'tres'],", (string) ConfigEditor::editList(self::FILE, 'everyday', 'tres', true));
        $this->assertStringContainsString("'legit' => ['calle'],", (string) ConfigEditor::editList(self::FILE, 'legit', 'calle', true));
    }
}
