<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The examples are runnable documentation: if the API moves and one breaks, fail here. */
class ExamplesRunTest extends TestCase
{
    public static function examples(): array
    {
        $cases = [];
        foreach (glob(dirname(__DIR__, 2) . '/examples/*.php') as $file) {
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

        $this->assertSame(0, $exitCode, "Example exited with code $exitCode:\n$text");
        $this->assertDoesNotMatchRegularExpression('/(Warning|Notice|Deprecated|Fatal error):/', $text, $text);
    }
}
