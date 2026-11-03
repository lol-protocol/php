<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\Parser\RecordSplitter;

class RecordSplitterTest extends TestCase
{
    /** @return array<string, array{list<string>, array<int, list<string>>}> */
    public static function blocks(): array
    {
        return [
            'un registro' => [
                ['HALL, Ruth', '3 Main Street', '555-123-4567'],
                [0 => ['HALL, Ruth', '3 Main Street', '555-123-4567']],
            ],
            'dos registros con teléfono' => [
                ['HALL, Ruth', '3 Main Street', '555-123-4567', 'LEE, Ken', '8 Oak Lane', '555-987-6543'],
                [0 => ['HALL, Ruth', '3 Main Street', '555-123-4567'], 3 => ['LEE, Ken', '8 Oak Lane', '555-987-6543']],
            ],
            'dos registros sin teléfono' => [
                ['HALL, Ruth', '3 Main Street', 'LEE, Ken', '8 Oak Lane'],
                [0 => ['HALL, Ruth', '3 Main Street'], 2 => ['LEE, Ken', '8 Oak Lane']],
            ],
            'línea de ciudad' => [
                ['SMITH, John', '12 Oak Street', 'Springfield', '555-123-4567'],
                [0 => ['SMITH, John', '12 Oak Street', 'Springfield', '555-123-4567']],
            ],
            'nota al final' => [
                ['SMITH, John', '12 Oak Street', '555-123-4567', 'Lawyer'],
                [0 => ['SMITH, John', '12 Oak Street', '555-123-4567', 'Lawyer']],
            ],
            'nombre sin calle después' => [
                ['HALL, Ruth', '3 Main Street', 'LEE, Ken', '555-987-6543'],
                [0 => ['HALL, Ruth', '3 Main Street', 'LEE, Ken', '555-987-6543']],
            ],
            'dos líneas de nombre antes de la calle' => [
                ['ACME Corp', 'Hardware', '3 Main Street'],
                [0 => ['ACME Corp', 'Hardware', '3 Main Street']],
            ],
            'vacío' => [[], [0 => []]],
            'menos de 4 líneas, sin mirar' => [['HALL, Ruth', '3 Main Street', 'LEE, Ken'], [0 => ['HALL, Ruth', '3 Main Street', 'LEE, Ken']]],
        ];
    }

    /**
     * @param list<string> $lines
     * @param array<int, list<string>> $expected
     */
    #[DataProvider('blocks')]
    public function testSplit(array $lines, array $expected): void
    {
        $records = RecordSplitter::split(
            $lines,
            fn($line) => preg_match('/^\d{3}-\d{3}-\d{4}$/', $line) === 1,
            fn($line) => preg_match('/\b(?:Street|Lane)\b/', $line) === 1
        );

        $this->assertSame($expected, $records);
    }

    public function testStreetCheckKnowsWhetherTheNameWasRead(): void
    {
        $calls = [];
        RecordSplitter::split(['ROJAS, Carmen', 'Los Aromos 12', 'PÉREZ, Ana', 'Las Lilas 4'], fn() => false, function ($line, $afterName) use (&$calls) {
            $calls[] = [$line, $afterName];
            return false;
        });

        $this->assertSame(
            [['ROJAS, Carmen', false], ['Los Aromos 12', true], ['PÉREZ, Ana', true], ['Las Lilas 4', true]],
            $calls
        );
    }
}
