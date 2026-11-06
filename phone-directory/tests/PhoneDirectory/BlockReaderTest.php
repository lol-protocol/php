<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\Parser\BlockReader;
use PhoneDirectory\Parser\LineFacts;
use PhoneDirectory\Parser\PhonePattern;

class BlockReaderTest extends TestCase
{
    /**
     * @param list<string> $lines
     * @return list<array{int, string|null, string|null, string|null}> [offset, name, street, phone] per record
     */
    private static function read(array $lines, string $language = 'en'): array
    {
        $regex = PhonePattern::regexFor($language);
        $records = BlockReader::read($lines, fn($line) => LineFacts::of($line, $language, $regex));

        return array_map(fn($r) => [$r['offset'], $r['name'], $r['street'], $r['phone']], $records);
    }

    /** @return array<string, array{list<string>, list<array{int, string|null, string|null, string|null}>}> */
    public static function blocks(): array
    {
        return [
            'un registro' => [
                ['HALL, Ruth', '3 Main Street', '555-123-4567'],
                [[0, 'HALL, Ruth', '3 Main Street', '555-123-4567']],
            ],
            'dos registros con teléfono' => [
                ['HALL, Ruth', '3 Main Street', '555-123-4567', 'LEE, Ken', '8 Oak Lane', '555-987-6543'],
                [[0, 'HALL, Ruth', '3 Main Street', '555-123-4567'], [3, 'LEE, Ken', '8 Oak Lane', '555-987-6543']],
            ],
            'dos registros sin teléfono' => [
                ['HALL, Ruth', '3 Main Street', 'LEE, Ken', '8 Oak Lane'],
                [[0, 'HALL, Ruth', '3 Main Street', null], [2, 'LEE, Ken', '8 Oak Lane', null]],
            ],
            'nombre y teléfono en la misma línea, dos registros' => [
                ['SMITH, John ..... 555-123-4567', '12 Oak Street', 'LEE, Ken ..... 555-987-6543', '8 Oak Lane'],
                [[0, 'SMITH, John', '12 Oak Street', '555-123-4567'], [2, 'LEE, Ken', '8 Oak Lane', '555-987-6543']],
            ],
            'línea de ciudad' => [
                ['SMITH, John', '12 Oak Street', 'Springfield', '555-123-4567'],
                [[0, 'SMITH, John', '12 Oak Street', '555-123-4567']],
            ],
            'nota al final' => [
                ['SMITH, John', '12 Oak Street', '555-123-4567', 'Lawyer'],
                [[0, 'SMITH, John', '12 Oak Street', '555-123-4567']],
            ],
            'nombre sin calle después' => [
                ['HALL, Ruth', '3 Main Street', 'LEE, Ken', '555-987-6543'],
                [[0, 'HALL, Ruth', '3 Main Street', '555-987-6543']],
            ],
            'palabra partida con guion' => [
                ['WASHING-', 'TON, George', '1 High Street'],
                [[0, 'WASHINGTON, George', '1 High Street', null]],
            ],
            'palabra partida en el segundo registro' => [
                ['HALL, Ruth', '3 Main Street', 'WASHING-', 'TON, George', '1 High Street'],
                [[0, 'HALL, Ruth', '3 Main Street', null], [2, 'WASHINGTON, George', '1 High Street', null]],
            ],
            'guion antes de un número no une' => [
                ['SMITH, John', '12 Oak Street', 'Tel.-', '555-123-4567'],
                [[0, 'SMITH, John', '12 Oak Street', '555-123-4567']],
            ],
            'vacío' => [[], [[0, null, null, null]]],
        ];
    }

    /**
     * @param list<string> $lines
     * @param list<array{int, string|null, string|null, string|null}> $expected
     */
    #[DataProvider('blocks')]
    public function testRead(array $lines, array $expected): void
    {
        $this->assertSame($expected, self::read($lines));
    }

    public function testAStreetWithoutAStreetWordGivesWayToALaterOneWithIt(): void
    {
        $this->assertSame(
            [[0, 'ACME Corp', 'Calle Mayor 5', null]],
            self::read(['ACME Corp', 'División 2', 'Calle Mayor 5'], 'es')
        );
        $this->assertSame(
            [[0, 'ROJAS, Carmen', 'Calle Mayor 5', null]],
            self::read(['ROJAS, Carmen', 'Calle Mayor 5', 'Los Aromos 12'], 'es'),
            'una calle con palabra no cede ante una sin ella'
        );
    }

    public function testNotesAreTheOtherLinesOfText(): void
    {
        $regex = PhonePattern::regexFor('es');
        $records = BlockReader::read(['GARCÍA, Juan', 'Farmacia', 'Calle Mayor 5', '555-123-4567'], fn($l) => LineFacts::of($l, 'es', $regex));

        $this->assertSame(['Farmacia'], $records[0]['notes']);
    }
}
