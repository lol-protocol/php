<?php

namespace PhoneDirectory\Parser;

/**
 * Reads a block of lines (the lines between two blank lines) into records, for both parsers, looking at
 * each line once (LineFacts).
 *
 * - A word the scan broke across two lines with a hyphen ("WASHING- / TON, George") is put back together,
 *   without the hyphen: a double-barrelled name cut at its own hyphen ("SMITH- / JONES") loses it, which is
 *   the lesser harm next to a record without its name.
 * - A block can hold several records with no blank line between them ("HALL, Ruth / 3 Main Street /
 *   555-123-4567 / LEE, Ken / 8 Oak Lane"). Once a record has its name and its street, a line that gives a
 *   name (a line of text, or a phone line with a name beside the phone) starts the next record, but only if
 *   a street follows it right away: a note after the phone ("Abogado") or a city line ("Springfield") stays
 *   in the record it closes.
 * - The record's name is its first line of text (or the name beside a phone, before any). Its street is the
 *   first street, unless that one had no street word and a later one does ("ACME Corp / División 2 / Calle
 *   Mayor 5" gives "Calle Mayor 5"). Its phone is the first phone. Any other line of text after the name is
 *   a note, which the multi-language parser reads for a business word.
 *
 * @phpstan-type BlockRecord array{offset: int, name: string|null, phone: string|null, street: string|null, notes: list<string>}
 */
final class BlockReader
{
    /**
     * @param list<string> $lines
     * @param callable(string): LineFacts $classify
     * @return list<BlockRecord> each record with the offset of its first line in $lines
     */
    public static function read(array $lines, callable $classify): array
    {
        [$lines, $origin] = self::joinHyphenatedWords($lines);
        $facts = array_map($classify, $lines);

        $records = [];
        $record = self::emptyRecord(0);
        $strongStreet = false;

        foreach ($facts as $i => $line) {
            $hasName = $record['name'] !== null;

            if ($hasName && $record['street'] !== null && $line->name(true) !== null
                && isset($facts[$i + 1]) && $facts[$i + 1]->street(true) !== null) {
                $records[] = $record;
                $record = self::emptyRecord($origin[$i]);
                $strongStreet = false;
                $hasName = false;
            }

            if ($record['phone'] === null && $line->phone !== null) {
                $record['phone'] = $line->phone;
            }

            $street = $line->street($hasName);
            if ($street !== null && ($record['street'] === null || (!$strongStreet && $line->strongStreet !== null))) {
                $record['street'] = $street;
                $strongStreet = $line->strongStreet !== null;
            }

            $name = $line->name($hasName);
            if (!$hasName && $name !== null) {
                $record['name'] = $name;
            } elseif ($hasName && $line->phone === null && $street === null) {
                $record['notes'][] = $line->line;
            }
        }

        $records[] = $record;

        return $records;
    }

    /** @return BlockRecord */
    private static function emptyRecord(int $offset): array
    {
        return ['offset' => $offset, 'name' => null, 'phone' => null, 'street' => null, 'notes' => []];
    }

    /**
     * @param list<string> $lines
     * @return array{list<string>, list<int>} the joined lines, and where each one starts in $lines
     */
    private static function joinHyphenatedWords(array $lines): array
    {
        $joined = [];
        $origin = [];
        foreach ($lines as $i => $line) {
            $last = count($joined) - 1;
            if ($last >= 0 && preg_match('/\p{L}-$/u', $joined[$last]) === 1 && preg_match('/^\p{L}/u', $line) === 1) {
                $joined[$last] = substr($joined[$last], 0, -1) . $line;
                continue;
            }
            $joined[] = $line;
            $origin[] = $i;
        }

        return [$joined, $origin];
    }
}
