<?php

namespace Tests\Review;

use DefamatoryContentReview\Review\{ReviewApplier, ReviewSheet};
use PHPUnit\Framework\TestCase;

/** Planilla llenada → cambios en config/, sobre una copia de spa. */
class ReviewSheetTest extends TestCase
{
    private const HEADER = ['seccion', 'termino', 'origen', 'categoria', 'riskType', 'severidad', 'marcas', 'rango', 'decision_chat', 'correcto', 'comentario'];

    private string $config;

    protected function setUp(): void
    {
        $this->config = sys_get_temp_dir() . '/review-' . bin2hex(random_bytes(4));
        foreach (['languages', 'chat-topics'] as $dir) {
            mkdir("{$this->config}/{$dir}", 0777, true);
            copy(__DIR__ . "/../../config/{$dir}/spa.php", "{$this->config}/{$dir}/spa.php");
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->config}/*/*.php"));
        array_map('rmdir', glob("{$this->config}/*"));
        rmdir($this->config);
    }

    /** @param array<int,array<int,string>> $rows */
    private function sheet(array $rows): ReviewSheet
    {
        return new ReviewSheet([self::HEADER, ...$rows]);
    }

    public function testTranslatesEachAnswerIntoAChange(): void
    {
        $sheet = $this->sheet([
            ['término', 'cerdo', 'diccionario', 'animal', 'animal', 'medium', '', '', 'review', 'alta', ''],
            ['término', 'marrana', 'diccionario', 'animal', 'animal', 'medium', '', '', 'review', 'no', ''],
            ['término', 'cerda', 'diccionario', 'animal', 'animal', 'medium', 'nameCollision', '', 'review', 'ambiguous', ''],
            ['frecuente', 'bomberos', 'bomba', 'belico', '', '', '', '900', 'review', 'NO', ''],
            ['término', 'chancho', 'diccionario', 'animal', 'animal', 'medium', '', '', 'review', 'sí', ''],
            ['término', 'puerco', 'diccionario', 'animal', 'animal', 'medium', '', '', 'review', '', ''],
            ['término', 'marrano', 'diccionario', 'animal', 'animal', 'medium', 'nameCollision', '', 'review', 'sin nameCollision', ''],
        ]);
        $plan = new ReviewApplier($sheet, $this->config, 'spa');

        $this->assertSame([], [...$sheet->problems, ...$plan->problems]);
        $this->assertCount(5, $plan->done);
        $this->assertSame(1, $sheet->pending);
        foreach ($plan->files as $path => $text) {
            file_put_contents($path, $text);
        }
        $words = array_merge(...array_values((require "{$this->config}/languages/spa.php")['words']));
        $byWord = array_column($words, null, 'word');
        $this->assertSame('high', $byWord['cerdo']['severity']);
        $this->assertArrayNotHasKey('marrana', $byWord);
        $this->assertTrue($byWord['cerda']['ambiguous']);
        $this->assertArrayNotHasKey('nameCollision', $byWord['marrano']);
        $this->assertContains('bomberos', (require "{$this->config}/chat-topics/spa.php")['everyday']);
    }

    public function testRefusesARowOlderThanTheConfiguration(): void
    {
        $sheet = $this->sheet([['término', 'cerdo', 'diccionario', 'animal', 'animal', 'low', '', '', 'review', 'no', '']]);
        $plan = new ReviewApplier($sheet, $this->config, 'spa');

        $this->assertSame([], $plan->files);
        $this->assertStringContainsString('regenérala', $plan->problems[0]);
    }

    public function testLeavesPatternsCommentsAndUnknownAnswersToAPerson(): void
    {
        $sheet = $this->sheet([
            ['patrón', 'amenaza', '\bte\s+mato\b', 'patterns', 'belico', 'high', '', '', '', 'no', ''],
            ['término', 'porno', 'temas', 'sexual', 'sexual', 'high', '', '', 'reject', 'sí', 'falta «pornazo»'],
            ['término', 'cerdo', 'diccionario', 'animal', 'animal', 'medium', '', '', 'review', 'quizá', ''],
        ]);

        $this->assertCount(2, $sheet->manual);
        $this->assertCount(1, $sheet->problems);
        $this->assertSame([], $sheet->changes);
    }
}
