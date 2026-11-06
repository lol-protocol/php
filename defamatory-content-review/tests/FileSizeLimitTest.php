<?php

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Regla del proyecto: ningún archivo de lógica supera 100 líneas de código, sin contar las vacías ni las de comentario (los diccionarios de config/ quedan exentos). */
class FileSizeLimitTest extends TestCase
{
    private const MAX_LINES = 100;

    /** @return array<string,array{string}> Recorre subcarpetas: un archivo movido a src/Chat/ o tests/Chat/ sigue vigilado. Los datos de tests/fixtures/ quedan fuera. */
    public static function logicFiles(): array
    {
        $root = dirname(__DIR__);
        $cases = [];
        foreach (['src', 'tests', 'examples', 'bin'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$root}/{$dir}", \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $path = substr($file->getPathname(), strlen($root) + 1);
                if ($file->getExtension() === 'php' && !str_starts_with($path, 'tests/fixtures/')) {
                    $cases[$path] = [$file->getPathname()];
                }
            }
        }
        ksort($cases);

        return $cases;
    }

    #[DataProvider('logicFiles')]
    public function testFileStaysWithinLineLimit(string $file): void
    {
        $lines = count(array_filter(file($file) ?: [], static fn(string $l): bool => trim($l) !== '' && preg_match('~^\s*(//|#(?!\[)|/?\*)~', $l) !== 1));

        $this->assertLessThanOrEqual(
            self::MAX_LINES,
            $lines,
            sprintf('%s tiene %d líneas de código (máximo %d): divídelo en colaboradores.', $file, $lines, self::MAX_LINES)
        );
    }

    /** @param array<int,string> $lines */
    private static function codeLines(array $lines): int
    {
        return count(array_filter($lines, static fn(string $l): bool => trim($l) !== '' && preg_match('~^\\s*(//|#(?!\\[)|/?\\*)~', $l) !== 1));
    }

    public function testCountsAttributesButNotCommentsOrBlankLines(): void
    {
        $this->assertSame(3, self::codeLines(["#[DataProvider('x')]\n", "public function f() {}\n", "\n", "// nota\n", "# nota\n", "/** doc */\n", " * doc\n", "return 1;\n"]));
    }
}
