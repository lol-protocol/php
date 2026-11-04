<?php

namespace PhoneDirectory\Parser;

/**
 * Splits a single "NAME ... STREET ... PHONE" line, the layout most historical directories actually use
 * (one printed line per person), as opposed to this project's original one-field-per-physical-line
 * assumption.
 *
 * Two strategies, tried in order:
 *
 * 1. Field splitting: the line has strong delimiters (commas, dot leaders, tabs, or runs of spaces/dashes),
 *    common in CSV-like exports and OCR output. Splitting on those gives clean fields, and the one that
 *    looks like a street (via $looksLikeStreet) can be anywhere in the line — first or last, since street
 *    number placement varies by convention ("12 Oak Street" vs "Calle Mayor 12").
 * 2. Digit-boundary splitting: no such delimiters (fields run together with single spaces, e.g.
 *    "SMITH John 12 Oak Street 555-123-4567"). Here the first digit in the line, once any phone number is
 *    removed, is assumed to start the street — true for the number-first convention this covers, since
 *    names in this project's supported languages essentially never contain digits.
 *    Where the house number comes last ("GARCÍA Juan Calle Mayor 12"), the first digit is the end of the
 *    street instead: a $streetStart callback, given the text before that digit, says where the street
 *    really begins (its street word), and that part moves from the name to the street.
 *
 * @phpstan-type SplitEntry array{name: string, street: string, phone: string|null}
 */
final class SingleLineEntrySplitter
{
    private const LEADER_CHARS = " \t\n\r\0\x0B.,-\xE2\x80\x93\xE2\x80\x94"; // includes en dash/em dash (UTF-8)

    // Requires at least 2 literal dots for a "dot leader" (so "Mrs. John" and "St. Louis" don't split on
    // their single abbreviation period) and at least 2 literal spaces or dashes for those to count either.
    private const FIELD_DELIMITER = '/\s*,\s*|(?:\.\s?){2,}|\t+|[ ]{2,}|[-\x{2013}\x{2014}]{2,}/u';

    /**
     * $surnameWordCount: with no delimiter to go by (the digit-boundary strategy), the name portion is
     * plain text like "SMITH John" or, for a 2-surname language, "GARCIA LOPEZ Juan" — this project's
     * directories are consistently surname-first even without a comma, so that many leading words are
     * read as the surname and the rest as the given name(s). Field splitting doesn't need this: its
     * fields already come out in surname-first order through the comma join in splitByFields().
     *
     * $streetStart: for a language that writes the house number after the street, a function that takes
     * the text before the first digit and returns the byte offset where the street starts in it, or null
     * if it finds none (the line is then split as before).
     *
     * @param (callable(string): ?int)|null $streetStart
     * @return SplitEntry|null
     */
    public static function split(
        string $line,
        callable $looksLikeStreet,
        int $surnameWordCount = 1,
        ?string $language = null,
        ?callable $streetStart = null
    ): ?array
    {
        // A phone the scan misread ("555-l23-4567") would otherwise pass for a street starting with a digit.
        $line = PhonePattern::repairOcrDigits($line);
        $fields = array_map('trim', preg_split(self::FIELD_DELIMITER, $line, -1, PREG_SPLIT_NO_EMPTY));

        if (count($fields) >= 2) {
            $result = self::splitByFields($fields, $looksLikeStreet, $language);
            if ($result !== null) {
                return $result;
            }
        }

        $result = self::splitByDigitBoundary($line, $looksLikeStreet, $language, $streetStart);
        if ($result !== null) {
            $result['name'] = self::assumeSurnameFirst($result['name'], $surnameWordCount);
        }

        return $result;
    }

    /** "SMITH John" -> "SMITH, John"; leaves an already-comma'd or too-short name alone. */
    private static function assumeSurnameFirst(string $name, int $surnameWordCount): string
    {
        if (str_contains($name, ',')) {
            return $name;
        }

        $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) <= $surnameWordCount) {
            return $name;
        }

        $surname = implode(' ', array_slice($words, 0, $surnameWordCount));
        $given = implode(' ', array_slice($words, $surnameWordCount));

        return "{$surname}, {$given}";
    }

    /**
     * @param string[] $fields
     * @return SplitEntry|null
     */
    private static function splitByFields(array $fields, callable $looksLikeStreet, ?string $language): ?array
    {
        $phone = null;
        foreach ($fields as $field) {
            if (PhonePattern::isOnlyAPhoneNumber($field, $language)) {
                $phone = $field;
                break;
            }
        }

        $streetIndex = null;
        foreach ($fields as $i => $field) {
            if ($field !== $phone && $looksLikeStreet($field)) {
                $streetIndex = $i;
                break;
            }
        }

        // No field alone reads as a street by its marker word; fall back to the first field that at
        // least starts with a digit (a street number with no recognized street-type word).
        if ($streetIndex === null) {
            foreach ($fields as $i => $field) {
                if ($field !== $phone && preg_match('/^\d/u', $field)) {
                    $streetIndex = $i;
                    break;
                }
            }
        }

        // A street field is only meaningful with a name field before it in the same record.
        if ($streetIndex === null || $streetIndex === 0) {
            return null;
        }

        $nameFields = [];
        foreach ($fields as $i => $field) {
            if ($i < $streetIndex && $field !== $phone) {
                $nameFields[] = $field;
            }
        }

        $name = implode(', ', $nameFields);
        $street = $fields[$streetIndex];

        // A name with no letters is a house number cut off by its comma ("12, rue de la Paix"), not a name.
        if (!self::hasLetter($name) || $street === '') {
            return null;
        }

        return ['name' => $name, 'street' => $street, 'phone' => $phone];
    }

    /**
     * @param (callable(string): ?int)|null $streetStart
     * @return SplitEntry|null
     */
    private static function splitByDigitBoundary(string $line, callable $looksLikeStreet, ?string $language, ?callable $streetStart): ?array
    {
        $rest = $line;
        $phone = null;

        if (preg_match(PhonePattern::regexFor($language), $line, $match, PREG_OFFSET_CAPTURE)) {
            $phone = $match[0][0];
            $offset = $match[0][1];
            // A line that opens with the phone ("555-123-4567 ext. 12") is the end of a record, not a whole one.
            if (!self::hasLetter(substr($line, 0, $offset))) {
                return null;
            }
            $rest = substr($line, 0, $offset) . substr($line, $offset + strlen($phone));
        }

        if (!preg_match('/\d.*/us', $rest, $tail, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $name = self::trimLeaders(substr($rest, 0, $tail[0][1]));
        $street = self::trimLeaders($tail[0][0]);

        $cut = $streetStart !== null && $name !== '' ? $streetStart($name) : null;
        if ($cut !== null && $cut > 0 && $cut < strlen($name)) {
            $street = substr($name, $cut) . ' ' . $street;
            $name = self::trimLeaders(substr($name, 0, $cut));
        }

        if ($name === '' || $street === '') {
            return null;
        }

        // With no phone and no recognized street word, a bare "word + number" is too ambiguous to split
        // out of its surrounding lines (it could be a continuation line, not a self-contained record).
        if ($phone === null && !$looksLikeStreet($street)) {
            return null;
        }

        return ['name' => $name, 'street' => $street, 'phone' => $phone];
    }

    private static function hasLetter(string $text): bool
    {
        return preg_match('/\p{L}/u', $text) === 1;
    }

    private static function trimLeaders(string $text): string
    {
        return trim($text, self::LEADER_CHARS);
    }
}
