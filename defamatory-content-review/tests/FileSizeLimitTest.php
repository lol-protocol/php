<?php

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Regla del proyecto: ningún archivo de lógica supera 100 líneas (los diccionarios de config/ quedan exentos). */
class FileSizeLimitTest extends TestCase
{
    private const MAX_LINES = 100;

    public static function logicFiles(): array
    {
        $root = dirname(__DIR__);
        $files = array_merge(
            glob($root . '/src/DefamatoryContentReview/*.php'),
            glob($root . '/tests/*.php'),
            glob($root . '/examples/*.php'),
            glob($root . '/bin/*.php')
        );

        $cases = [];
        foreach ($files as $file) {
            $cases[substr($file, strlen($root) + 1)] = [$file];
        }

        return $cases;
    }

    #[DataProvider('logicFiles')]
    public function testFileStaysWithinLineLimit(string $file): void
    {
        $lines = count(file($file));

        $this->assertLessThanOrEqual(
            self::MAX_LINES,
            $lines,
            sprintf('%s tiene %d líneas (máximo %d): divídelo en colaboradores.', $file, $lines, self::MAX_LINES)
        );
    }
}
