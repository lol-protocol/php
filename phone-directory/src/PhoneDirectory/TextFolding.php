<?php

namespace PhoneDirectory;

use DefamatoryContentReview\AccentFolding;
use PhoneDirectory\Exception\InvalidEncodingException;

final class TextFolding
{
    /**
     * Lowercase and strip accents so "García" and "garcia" compare equal.
     *
     * The input is validated before transforming it: mb_strtolower() silently replaces invalid
     * bytes with '?', so checking afterwards would never catch non-UTF-8 input.
     *
     * @throws InvalidEncodingException If the text is not valid UTF-8
     */
    public static function fold(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidEncodingException('Invalid UTF-8 encoding in text');
        }

        $folded = AccentFolding::fold(mb_strtolower($text, 'UTF-8'));

        if (!mb_check_encoding($folded, 'UTF-8')) {
            throw new InvalidEncodingException('Invalid UTF-8 encoding in text after accent folding');
        }

        return $folded;
    }
}
