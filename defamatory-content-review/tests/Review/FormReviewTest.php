<?php

namespace Tests\Review;

use DefamatoryContentReview\Review\{FormEditor, ReviewApplier, ReviewSheet};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** La respuesta del revisor sobre una forma generada («cerdos», «cerdas») va al `by` de su entrada. */
class FormReviewTest extends TestCase
{
    private const HEADER = ['seccion', 'termino', 'origen', 'categoria', 'riskType', 'severidad', 'marcas', 'rango', 'decision_chat', 'correcto', 'comentario'];

    public function testWritesTheSeverityOfAFormIntoBy(): void
    {
        $plain = "['word' => 'cerdo', 'riskType' => 'animal', 'severity' => 'medium', 'forms' => 'adj'],";
        $withBy = "['word' => 'cerdo', 'riskType' => 'animal', 'severity' => 'medium', 'forms' => 'adj', 'by' => ['f' => 'high']],";

        $this->assertSame(
            "['word' => 'cerdo', 'riskType' => 'animal', 'severity' => 'medium', 'forms' => 'adj', 'by' => ['m.pl' => 'none']],",
            FormEditor::setBy($plain, 'cerdo', 'm.pl', 'none')
        );
        $this->assertStringContainsString("'by' => ['f.pl' => 'medium', 'f' => 'high']", (string) FormEditor::setBy($withBy, 'cerdo', 'f.pl', 'medium'));
        $this->assertStringContainsString("'by' => ['f' => 'low']", (string) FormEditor::setBy($withBy, 'cerdo', 'f', 'low'));
        $this->assertNull(FormEditor::setBy("['word' => 'rata', 'severity' => 'medium'],", 'rata', 'pl', 'high'), 'sin forms, by no haría nada');
    }

    /** @return array<string,array{string,string}> [tag, severity] */
    public static function notFormValues(): array
    {
        return [
            'etiqueta con comilla' => ["f'", 'high'],
            'etiqueta que cierra y abre' => ["pl'], 'x", 'high'],
            'etiqueta con metacaracteres' => ['.*', 'high'],
            'etiqueta vacía' => ['', 'high'],
            'severidad con comilla' => ['pl', "high'"],
            'severidad desconocida' => ['pl', 'critical'],
            'severidad con igual' => ['pl', 'y=high'],
        ];
    }

    #[DataProvider('notFormValues')]
    public function testSetByRefusesWhatTheFormatDoesNotAllow(string $tag, string $severity): void
    {
        $entry = "['word' => 'cerdo', 'riskType' => 'animal', 'severity' => 'medium', 'forms' => 'adj', 'by' => ['f' => 'high']],";

        $this->assertNull(FormEditor::setBy($entry, 'cerdo', $tag, $severity));
    }

    public function testAppliesAFormAnswerFromASheet(): void
    {
        $config = sys_get_temp_dir() . '/forms-' . bin2hex(random_bytes(4));
        mkdir("{$config}/languages", 0777, true);
        copy(__DIR__ . '/../../config/languages/spa.php', "{$config}/languages/spa.php");
        $sheet = new ReviewSheet([self::HEADER,
            ['forma', 'cerdos', 'diccionario', 'animal', 'animal', 'medium', 'forma:m.pl de:cerdo', '', 'review', 'no', ''],
            ['forma', 'putas', 'diccionario', 'genero', 'genero', 'high', 'forma:pl de:puta', '', 'reject', 'media', ''],
        ]);
        $plan = new ReviewApplier($sheet, $config, 'spa');
        foreach ($plan->files as $path => $text) {
            file_put_contents($path, $text);
        }
        $entries = array_column(array_merge(...array_values((require "{$config}/languages/spa.php")['words'])), null, 'word');
        unlink("{$config}/languages/spa.php");
        rmdir("{$config}/languages");
        rmdir($config);

        $this->assertSame([], [...$sheet->problems, ...$plan->problems]);
        $this->assertEquals(['f' => 'high', 'm.pl' => 'none'], $entries['cerdo']['by'], 'se suma a lo que ya había');
        $this->assertSame('medium', $entries['puta']['by']['pl']);
    }
}
