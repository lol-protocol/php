<?php

namespace Tests\Review;

use DefamatoryContentReview\Review\ConfigEditor;
use PHPUnit\Framework\TestCase;

/** Cambios de una línea sobre los archivos de config/, sin tocar el resto. */
class ConfigEditorTest extends TestCase
{
    private const FILE = <<<'PHP'
        <?php

        // comentario que debe sobrevivir
        return [
            'meta' => ['code' => 'xxx'],
            'everyday' => ['uno', 'dos'],
            'words' => [
                'animal' => [
                    ['word' => 'cerdo', 'riskType' => 'animal', 'severity' => 'medium'],
                    ['word' => 'd\'oh', 'riskType' => 'animal', 'severity' => 'low'],
                    ['word' => 'degollar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb',
                        'also' => ['degüello', 'degüella']],
                    ['word' => 'rata', 'riskType' => 'animal', 'severity' => 'medium'],
                ],
            ],
        ];
        PHP;

    public function testChangesTheSeverityOfOneEntryOnly(): void
    {
        $text = ConfigEditor::setSeverity(self::FILE, 'cerdo', 'high');

        $this->assertStringContainsString("['word' => 'cerdo', 'riskType' => 'animal', 'severity' => 'high'],", $text);
        $this->assertStringContainsString("['word' => 'rata', 'riskType' => 'animal', 'severity' => 'medium'],", $text);
        $this->assertStringContainsString('// comentario que debe sobrevivir', $text);
    }

    public function testFlagsAnEntryThatSpansSeveralLines(): void
    {
        $text = ConfigEditor::addFlag(self::FILE, 'degollar', 'ambiguous');

        $this->assertStringContainsString("'also' => ['degüello', 'degüella'], 'ambiguous' => true],", $text);
        $this->assertSame($text, ConfigEditor::addFlag($text, 'degollar', 'ambiguous'), 'una marca no se repite');
    }

    public function testRemovesTheWholeEntryEvenAcrossLines(): void
    {
        $text = ConfigEditor::remove(self::FILE, 'degollar');

        $this->assertStringNotContainsString('degollar', $text);
        $this->assertStringNotContainsString('degüello', $text);
        $this->assertSame(substr_count(self::FILE, "\n") - 2, substr_count($text, "\n"));
    }

    public function testDoesNotConfuseAWordWithOneThatStartsTheSame(): void
    {
        $this->assertNull(ConfigEditor::entry(self::FILE, 'cerd'));
        $this->assertNull(ConfigEditor::setSeverity(self::FILE, 'no-existe', 'high'));
    }

    public function testEditsTheOneLineLists(): void
    {
        $added = ConfigEditor::editList(self::FILE, 'everyday', 'tres', true);
        $this->assertStringContainsString("'everyday' => ['uno', 'dos', 'tres'],", $added);
        $this->assertStringContainsString("'everyday' => ['dos'],", ConfigEditor::editList(self::FILE, 'everyday', 'uno', false));
        $this->assertNull(ConfigEditor::editList(self::FILE, 'everyday', 'cuatro', false));
    }

    public function testCreatesAMissingListBeforeTheWords(): void
    {
        $text = ConfigEditor::editList(self::FILE, 'legit', 'calle', true);

        $this->assertStringContainsString("    'legit' => ['calle'],\n    'words' => [", $text);
    }

    public function testEveryEditStillEvaluatesToTheSameArrayShape(): void
    {
        foreach ([
            ConfigEditor::setSeverity(self::FILE, 'cerdo', 'low'),
            ConfigEditor::addFlag(self::FILE, "d'oh", 'nameCollision'),
            ConfigEditor::remove(self::FILE, 'rata'),
            ConfigEditor::editList(self::FILE, 'legit', 'calle', true),
        ] as $text) {
            $this->assertNotNull($text);
            $data = eval('?>' . $text);
            $this->assertIsArray($data['words']['animal']);
        }
    }
}
