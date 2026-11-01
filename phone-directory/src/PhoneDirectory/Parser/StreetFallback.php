<?php

namespace PhoneDirectory\Parser;

/**
 * Recognizes a street that has no street-type word ("9 Wexford Close", "8 Clos Saint-Éloi"): the whole line
 * must be a house number followed only by words. Both parsers use it after their street-word check fails.
 *
 * It used to accept any "digits, space, letters" anywhere in the line, which read the middle of a business
 * name ("Restaurante 3 Hermanos") or an apartment line ("Apt 4 Building 7") as a street, and, matching only
 * unaccented letters, cut "8 Clos Saint-Éloi" down to "8 Clos Saint" or missed "14 Ó Briain Close" entirely.
 *
 * Too weak to split a single-line record on its own: the splitter keeps asking for a street word there.
 */
final class StreetFallback
{
    private const NUMBER_FIRST = "/^\\d{1,5}[a-z]?\\s+\\p{L}[\\p{L}\\p{M}'’.\\-]*(?:\\s+\\p{L}[\\p{L}\\p{M}'’.\\-]*)*$/iu";

    private const NUMBER_LAST = "/^\\p{L}[\\p{L}\\p{M}'’.\\-]*(?:\\s+\\p{L}[\\p{L}\\p{M}'’.\\-]*)*\\s+\\d{1,5}[a-z]?$/iu";

    public static function numberFirst(string $line): ?string
    {
        $trimmed = trim($line);

        return mb_check_encoding($trimmed, 'UTF-8') && preg_match(self::NUMBER_FIRST, $trimmed) === 1 ? $trimmed : null;
    }

    /**
     * "Los Aromos 123", "Travessa do Carmo 5": only words, then the house number. The usual order in Spanish,
     * Portuguese, Italian and German addresses, but also the shape of a name like "Restaurante Siglo 21", so
     * callers only ask once the record's name has been read.
     */
    public static function numberLast(string $line): ?string
    {
        $trimmed = trim($line);

        return mb_check_encoding($trimmed, 'UTF-8') && preg_match(self::NUMBER_LAST, $trimmed) === 1 ? $trimmed : null;
    }
}
