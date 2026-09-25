<?php

namespace PhoneDirectory\Parser;

/**
 * Phone formats recognized across both parsers: standard digit groups (555-123-4567, 5551234567) and the
 * named-exchange scheme used in early-to-mid 20th century US/UK directories ("BUtterfield 8-4521",
 * "PEnnsylvania 6-5000" = PE6-5000). The exchange form requires an explicit separator between the word and
 * the digits on both sides, since real listings are always written that way; without that, a word glued
 * directly to a run of digits (an apartment number, a page reference) would false-positive as a phone.
 */
final class PhonePattern
{
    public const REGEX = '/\b[A-Za-z]{3,}[ .-]\d[ -]\d{4}\b|\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b|\b\d{10}\b/u';

    private static ?string $validatedRegex = null;

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

    /** True when $text, trimmed, is nothing but a single phone match (so it should not also be read as a street). */
    public static function isOnlyAPhoneNumber(string $text): bool
    {
        $trimmed = trim($text);

        return $trimmed !== '' && preg_match(self::getValidatedRegex(), $trimmed, $m) === 1 && $m[0] === $trimmed;
    }
}
