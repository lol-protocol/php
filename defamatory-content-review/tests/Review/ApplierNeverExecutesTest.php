<?php

namespace Tests\Review;

use DefamatoryContentReview\Review\{ReviewApplier, ReviewSheet};
use PHPUnit\Framework\TestCase;

/**
 * El texto que resulta de aplicar una planilla se lee con `require` para comprobar su forma. Antes de
 * leerlo tiene que ser sólo datos: si algo más llegara a colarse, no puede correr.
 */
class ApplierNeverExecutesTest extends TestCase
{
    private const HEADER = ['seccion', 'termino', 'origen', 'categoria', 'riskType', 'severidad', 'marcas', 'rango', 'decision_chat', 'correcto', 'comentario'];

    private string $config;

    protected function setUp(): void
    {
        $this->config = sys_get_temp_dir() . '/never-' . bin2hex(random_bytes(4));
        mkdir("{$this->config}/chat-topics", 0777, true);
        copy(__DIR__ . '/../../config/chat-topics/spa.php', "{$this->config}/chat-topics/spa.php");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->config}/*/*.php"));
        rmdir("{$this->config}/chat-topics");
        rmdir($this->config);
    }

    /** Un `define` es un canario inofensivo: si la constante existe después, el texto se ejecutó. */
    public function testTheResultingTextIsNeverExecuted(): void
    {
        $canary = 'REVIEW_CANARIO_' . strtoupper(bin2hex(random_bytes(4)));
        $path = "{$this->config}/chat-topics/spa.php";
        file_put_contents($path, preg_replace('/^return /m', "define('{$canary}', true);\nreturn ", (string) file_get_contents($path), 1));
        $sheet = new ReviewSheet([self::HEADER, ['frecuente', 'bomberos', 'bomba', 'belico', '', '', '', '900', 'review', 'no', '']]);

        $plan = new ReviewApplier($sheet, $this->config, 'spa');

        $this->assertFalse(defined($canary), 'el texto resultante se ejecutó');
        $this->assertCount(1, $plan->problems);
        $this->assertStringContainsString('no es un dato', $plan->problems[0]);
    }

    public function testASyntaxErrorIsStillAProblemAndNotACrash(): void
    {
        $path = "{$this->config}/chat-topics/spa.php";
        file_put_contents($path, (string) file_get_contents($path) . "\n]];\n");
        $sheet = new ReviewSheet([self::HEADER, ['frecuente', 'bomberos', 'bomba', 'belico', '', '', '', '900', 'review', 'no', '']]);

        $plan = new ReviewApplier($sheet, $this->config, 'spa');

        $this->assertCount(1, $plan->problems);
        $this->assertStringContainsString('no es PHP válido', $plan->problems[0]);
    }

    public function testAnEditThatKeepsTheFileAsDataIsAccepted(): void
    {
        $sheet = new ReviewSheet([self::HEADER, ['frecuente', 'bomberos', 'bomba', 'belico', '', '', '', '900', 'review', 'no', '']]);

        $plan = new ReviewApplier($sheet, $this->config, 'spa');

        $this->assertSame([], $plan->problems);
        $this->assertCount(1, $plan->files);
    }
}
