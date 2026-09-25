<?php

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Los ejemplos de examples/ son documentación ejecutable: si la API cambia y uno se rompe, falla aquí. */
class ExamplesRunTest extends TestCase
{
    public static function examples(): array
    {
        $cases = [];
        foreach (glob(dirname(__DIR__) . '/examples/*.php') as $file) {
            $cases[basename($file)] = [$file];
        }

        return $cases;
    }

    #[DataProvider('examples')]
    public function testExampleRunsWithoutErrors(string $file): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' -d error_reporting=-1 -d display_errors=1 ' . escapeshellarg($file) . ' 2>&1';
        exec($command, $output, $exitCode);
        $text = implode("\n", $output);

        $this->assertSame(0, $exitCode, "El ejemplo terminó con código $exitCode:\n$text");
        $this->assertDoesNotMatchRegularExpression('/(Warning|Notice|Deprecated|Fatal error):/', $text, $text);
        $this->assertNotSame('', trim($text), 'El ejemplo no imprimió nada.');
    }
}
