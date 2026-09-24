<?php

namespace DefamatoryContentReview;

/** Utility functions for string operations. Reduces verbosity in filters and comparisons. */
final class StringUtils
{
    /** UTF-8 aware string length check: true if length >= minimum. */
    public static function lenGe(string $s, int $minLength): bool
    {
        return mb_strlen($s, 'UTF-8') >= $minLength;
    }

    /** UTF-8 aware string length. Shorthand for mb_strlen(..., 'UTF-8'). */
    public static function len(string $s): int
    {
        return mb_strlen($s, 'UTF-8');
    }
}
