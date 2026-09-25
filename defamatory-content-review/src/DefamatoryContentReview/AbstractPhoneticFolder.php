<?php

namespace DefamatoryContentReview;

/** Base class for language-specific phonetic folding. Extracts common initialization, each subclass defines only the language-specific rules. */
abstract class AbstractPhoneticFolder
{
    use LeetspeakFolding;

    /** Accent/diacritic mappings for this language. */
    abstract protected static function getAccents(): array;

    /** Apply language-specific phonetic rules. Input is already normalized (lowercase, unleetified, accents reduced). */
    abstract protected static function applyLanguageRules(string $text): string;

    /** Complete fold: normalize, then apply language rules. */
    final public static function fold(string $text): string
    {
        $text = self::normalize($text);
        return static::applyLanguageRules($text);
    }

    /** Shared initialization: lowercase, trim, unleet, strip accents, strip separators. */
    protected static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, static::getAccents());
        return self::stripSeparators($text);
    }
}
