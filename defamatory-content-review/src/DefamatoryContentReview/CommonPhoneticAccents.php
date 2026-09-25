<?php

namespace DefamatoryContentReview;

/** Shared accent/diacritical mark mappings used across multiple language folders. Reduces duplication. */
final class CommonPhoneticAccents
{
    /** Base European vowel accents used in Spanish, French, Italian, Portuguese, German, etc. */
    public const EUROPEAN_VOWELS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
    ];

    /** Nordic vowel expansions (Danish, Norwegian, Swedish, German use these alternatives). */
    public const NORDIC_VOWELS = [
        'ä' => 'ae', 'ö' => 'oe', 'å' => 'aa',
    ];

    /** Merge base European vowels with language-specific additions (e.g., Spanish ñ). */
    public static function withExtras(array ...$extras): array
    {
        $result = self::EUROPEAN_VOWELS;
        foreach ($extras as $extra) {
            $result = array_merge($result, $extra);
        }
        return $result;
    }
}
