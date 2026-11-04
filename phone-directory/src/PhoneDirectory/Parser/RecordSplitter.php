<?php

namespace PhoneDirectory\Parser;

/**
 * Splits a block that holds several records with no blank line between them ("HALL, Ruth / 3 Main
 * Street / 555-123-4567 / LEE, Ken / 8 Oak Lane"). Once a record has its name and its street, a line
 * that is neither street nor phone starts the next record, but only if a street follows it right away:
 * a note after the phone ("Abogado") or a city line ("Springfield") stays in the record it closes.
 *
 * Before that, a word the scan broke across two lines with a hyphen ("WASHING- / TON, George") is put back
 * together, without the hyphen: a double-barrelled name cut at its own hyphen ("SMITH- / JONES") loses it,
 * which is the lesser harm next to a record without its name.
 *
 * Both parsers use it, each with its own idea of what a phone and a street are.
 */
final class RecordSplitter
{
    /**
     * $isStreet gets the line and whether the record's name has already been read (a number-last street
     * without a street word only counts after it).
     *
     * @param list<string> $lines
     * @param callable(string): bool $isPhone
     * @param callable(string, bool): bool $isStreet
     * @return array<int, list<string>> each record's lines, keyed by the offset of its first line in $lines
     */
    public static function split(array $lines, callable $isPhone, callable $isStreet): array
    {
        [$lines, $origin] = self::joinHyphenatedWords($lines);

        // Two records need a name and a street each: a shorter block (the usual 3-line record) is one.
        if (count($lines) < 4) {
            return [0 => $lines];
        }

        $records = [];
        $start = 0;
        $hasName = false;
        $hasStreet = false;

        foreach ($lines as $i => $line) {
            $isPhoneLine = $isPhone($line);
            $isStreetLine = $isStreet($line, $hasName);

            $next = $lines[$i + 1] ?? null;
            if ($hasName && $hasStreet && !$isPhoneLine && !$isStreetLine
                && $next !== null && $isStreet($next, true)) {
                $records[$origin[$start]] = array_slice($lines, $start, $i - $start);
                $start = $i;
                $hasStreet = false;
                continue;
            }

            $hasName = $hasName || (!$isPhoneLine && !$isStreetLine);
            $hasStreet = $hasStreet || $isStreetLine;
        }

        $records[$origin[$start]] = array_slice($lines, $start);

        return $records;
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
