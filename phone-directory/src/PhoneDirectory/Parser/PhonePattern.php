<?php

namespace PhoneDirectory\Parser;

/**
 * Phone formats recognized across both parsers: standard digit groups (555-123-4567, 5551234567) and the
 * named-exchange scheme used in early-to-mid 20th century US/UK directories ("BUtterfield 8-4521",
 * "PEnnsylvania 6-5000" = PE6-5000). The exchange form requires an explicit separator between the word and
 * the digits on both sides, since real listings are always written that way; without that, a word glued
 * directly to a run of digits (an apartment number, a page reference) would false-positive as a phone.
 *
 * regexFor() adds the formats of each language's countries on top ("01 42 68 53 00", "030 123456",
 * "06 1234567", "21 342 0000", "91 234 56 78", "2-345-678"...). They are kept per language because the
 * looser ones would read a house number as part of a phone in another language's directory. Every added
 * format refuses to start or end next to another digit group ("Calle Mayor 12 555-123-4567" must not
 * give "12 555-123"), which also keeps it off a street number followed by a phone.
 */
final class PhonePattern
{
    public const REGEX = '/\b[A-Za-z]{3,}[ .-]\d[ -]\d{4}\b|\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b|\b\d{10}\b/u';

    // Not preceded by a digit or a "+", not followed by a separator and a digit. A house number just before
    // the phone ("Hauptstraße 5 030 123456") is fine: the phone starts at its own first digit, and a format
    // that would also take the house number ("12 555-123") then runs into the rest of the number and fails.
    private const BEFORE = '(?<![\d+])';
    private const AFTER = '(?![ .\/-]?\d)';

    private const LANGUAGE_FORMATS = [
        // 01 42 68 53 00, 01.42.68.53.00, +33 1 42 68 53 00
        'fr' => ['(?:\+33[ .]?[1-9]|0[1-9])(?:[ .]?\d{2}){4}'],
        // 030 123456, (089) 654321, 0711/12345, 030 12345-67, +49 30 123456
        'de' => ['(?:\+49[ ]?[1-9]\d{1,3}|\(0\d{2,4}\)|0\d{2,4})[ \/-]?\d{3,8}(?:-\d{1,4})?'],
        // 06 1234567, 02/12345678, +39 06 1234567
        'it' => ['(?:\+39[ ]?)?0\d{1,3}[ \/-]?\d{4,8}'],
        // 21 342 0000, 213 420 000, +351 21 342 0000
        'pt' => ['(?:\+351[ ]?)?(?:[2-9]\d[ ]\d{3}[ ]\d{4}|[2-9]\d{2}[ ]\d{3}[ ]\d{3})'],
        // Spain: 91 234 56 78, 912 345 678; Chile and others: 2-345-678, 2-555-1212, (2) 345 6789
        'es' => [
            '(?:\+34[ ]?)?[6-9]\d[ ]\d{3}(?:[ ]\d{2}){2}',
            '(?:\(\d{1,3}\)[ ]?|\d{1,3}[ -])\d{3}[ -]\d{3,4}',
        ],
    ];

    private static ?string $validatedRegex = null;

    /** @var array<string, string> */
    private static array $languageRegexCache = [];

    public static function getValidatedRegex(): string
    {
        if (self::$validatedRegex !== null) {
            return self::$validatedRegex;
        }

        if (@preg_match(self::REGEX, '') === false) {
            throw new \InvalidArgumentException('Invalid phone pattern regex: ' . preg_last_error_msg());
        }

        self::$validatedRegex = self::REGEX;
        return self::$validatedRegex;
    }

    /** The base formats plus those of $language's countries; null, or a language without its own, gives the base. */
    public static function regexFor(?string $language): string
    {
        $formats = self::LANGUAGE_FORMATS[$language ?? ''] ?? [];
        if ($formats === []) {
            return self::getValidatedRegex();
        }

        if (!isset(self::$languageRegexCache[$language])) {
            $extra = implode('|', array_map(fn($format) => self::BEFORE . '(?:' . $format . ')' . self::AFTER, $formats));
            $regex = substr(self::REGEX, 0, -2) . '|' . $extra . '/u';
            if (@preg_match($regex, '') === false) {
                throw new \InvalidArgumentException("Invalid phone pattern regex for {$language}: " . preg_last_error_msg());
            }
            self::$languageRegexCache[$language] = $regex;
        }

        return self::$languageRegexCache[$language];
    }

    /**
     * Undoes the usual OCR misreadings inside what looks like a phone number: "l" or "I" for 1 and "O" or
     * "o" for 0 ("555-l23-4567" gives "555-123-4567"). Only in a run of digits and separators with at least
     * 6 real digits and at most 2 such letters, so words ("Oil", "Ollie") and exchange names
     * ("BUtterfield 8-4521") are left alone. Returns the line unchanged when there is nothing to repair.
     */
    public static function repairOcrDigits(string $line): string
    {
        // Cheap test first: most lines have no such letter next to a digit, and this runs on every line.
        if (preg_match('/\d[ .\-]?[OolI]|[OolI][ .\-]?\d/', $line) !== 1) {
            return $line;
        }

        return (string) preg_replace_callback(
            '/(?<![\p{L}\d])[\dOolI](?:[ .\-]?[\dOolI])+(?![\p{L}\d])/u',
            function (array $m): string {
                $digits = preg_match_all('/\d/', $m[0]);
                $letters = preg_match_all('/[OolI]/', $m[0]);
                return $letters > 0 && $letters <= 2 && $digits >= 6 ? strtr($m[0], 'OolI', '0011') : $m[0];
            },
            $line
        );
    }

    /**
     * What a line says besides its phone, when that could be a name: "SMITH, John ........ 555-123-4567"
     * gives "SMITH, John". Null when nothing is left or what is left has a digit ("555-123-4567 ext. 12").
     */
    public static function nameBesidePhone(string $line, string $phone): ?string
    {
        $offset = strpos($line, $phone);
        if ($offset === false) {
            return null;
        }

        $rest = trim(substr($line, 0, $offset) . ' ' . substr($line, $offset + strlen($phone)), " \t.,:;-\u{2013}\u{2014}");

        return preg_match('/\p{L}/u', $rest) === 1 && preg_match('/\d/', $rest) !== 1 ? $rest : null;
    }

    /** True when $text, trimmed, is nothing but a single phone match (so it should not also be read as a street). */
    public static function isOnlyAPhoneNumber(string $text, ?string $language = null): bool
    {
        $trimmed = trim($text);

        return $trimmed !== '' && preg_match(self::regexFor($language), $trimmed, $m) === 1 && $m[0] === $trimmed;
    }
}
