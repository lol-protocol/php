<?php

namespace PhoneDirectory\Parser;

use PhoneDirectory\Exception\InvalidLanguageException;

/**
 * What counts as a street, per language, for both parsers (PhoneDirectoryParser reads English).
 *
 * A line is a street in one of two strengths:
 *  - strong: it has a street word ("Calle", "Street", "-straße"...);
 *  - weak: it has none but has the shape of an address (StreetFallback): a house number first and only
 *    words after it, in any language; and, once the record's name is read, the house number last (es, pt,
 *    it, de) or a dwelling line (en, "Apt 4 Building 7").
 * A weak street gives way to a strong one later in the same record ("ACME Corp / División 2 / Calle Mayor 5").
 *
 * Some English street words are also common abbreviations at the start of a name: "Dr." (doctor) and
 * "St." (saint). They only make a street when the line has a digit or they are not its first word, so
 * "Dr. SMITH, John" and "St. Mary Bakery" stay names while "12 Oak St" and "Oak St" stay streets.
 */
final class StreetRules
{
    // BEGIN generated: palabras/calles (de data/palabras.json: no se edita aquí, ver bin/generar-palabras.php)
    public const SUPPORTED_LANGUAGES = ['es', 'en', 'fr', 'pt', 'de', 'it'];

    // Languages whose addresses put the house number after the street name ("Calle Mayor 12", "Via Roma 10").
    public const NUMBER_LAST_LANGUAGES = ['es', 'pt', 'it', 'de'];

    private const MARKERS = [
        'es' => ['calle', 'c/', 'avenida', 'av', 'plaza', 'pasaje', 'camino', 'ruta', 'carrera'],
        'en' => ['street', 'st', 'avenue', 'ave', 'road', 'rd', 'drive', 'dr', 'lane', 'ln', 'boulevard', 'blvd', 'circle', 'cir'],
        'fr' => ['rue', 'avenue', 'allée', 'place', 'boulevard', 'bd', 'cours', 'square'],
        'pt' => ['rua', 'avenida', 'av', 'praça', 'alameda', 'estrada', 'largo'],
        'de' => ['ring', 'hof'],
        'it' => ['via', 'viale', 'corso', 'piazza', 'largo', 'strada'],
    ];

    // German compounds glue the street type onto the name ("Hauptstraße"), so these match as word endings.
    public const SUFFIXES = [
        'de' => ['straße', 'strasse', 'allee', 'weg', 'platz'],
    ];

    // Street words that are also the usual abbreviation opening a name (see the class comment).
    private const NAME_ABBREVIATIONS = [
        'en' => ['dr', 'st'],
    ];
    // END generated: palabras/calles

    /** @var array<string, string> */
    private static array $patternCache = [];

    /** @var array<string, string|null> */
    private static array $abbreviationCache = [];

    /** Whether the line has a street word (a strong street). */
    public static function hasStreetWord(string $line, string $language): bool
    {
        if (!mb_check_encoding($line, 'UTF-8')) {
            return false;
        }

        if (preg_match(self::pattern($language), $line) === 1) {
            return true;
        }

        $abbreviation = self::abbreviationPattern($language);
        if ($abbreviation === null || preg_match_all($abbreviation, $line, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return false;
        }

        if (preg_match('/\d/', $line) === 1) {
            return true;
        }

        foreach ($matches[0] as [, $offset]) {
            if (trim(substr($line, 0, $offset)) !== '') {
                return true;
            }
        }

        return false;
    }

    /** The line, when it is a street with a street word or a house number first; null otherwise. */
    public static function strong(string $line, string $language): ?string
    {
        if (self::hasStreetWord($line, $language)) {
            return $line;
        }

        if (!mb_check_encoding($line, 'UTF-8') || PhonePattern::isOnlyAPhoneNumber($line, $language)) {
            return null;
        }

        return StreetFallback::numberFirst($line);
    }

    /**
     * An address that only counts once the record's name is read: in a language that writes the house number
     * last, a street with no street word ("Los Aromos 123"); in English, a dwelling line ("Apt 4 Building 7").
     */
    public static function afterName(string $line, string $language): ?string
    {
        if ($language === 'en') {
            return StreetFallback::dwelling($line);
        }

        return in_array($language, self::NUMBER_LAST_LANGUAGES, true) ? StreetFallback::numberLast($line) : null;
    }

    /**
     * Where the street starts in the text before a number-last house number ("GARCÍA Juan Calle Mayor"
     * gives the offset of "Calle"): the first street word after the first two words, which belong to the
     * name, so a surname that is also a street word ("PLAZA ROJAS Juan Calle Mayor") stays in the name.
     * A street word that is a suffix ("Hauptstraße") starts the street at the beginning of its word.
     */
    public static function streetWordOffset(string $text, string $language): ?int
    {
        if (!preg_match_all(self::pattern($language), $text, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        foreach ($matches[0] as [, $offset]) {
            preg_match('/[\p{L}\p{M}\'’.\-]*$/u', substr($text, 0, $offset), $wordStart);
            $offset -= strlen($wordStart[0]);
            if (preg_match_all('/\S+/u', substr($text, 0, $offset)) >= 2) {
                return $offset;
            }
        }

        return null;
    }

    /** The street words of $language, as a regex; the abbreviations of NAME_ABBREVIATIONS are not in it. */
    public static function pattern(string $language): string
    {
        if (!isset(self::$patternCache[$language])) {
            if (!isset(self::MARKERS[$language])) {
                throw new InvalidLanguageException("Unsupported language for street pattern: {$language}. Supported languages: " . implode(', ', self::SUPPORTED_LANGUAGES));
            }

            $markers = array_diff(self::MARKERS[$language], self::NAME_ABBREVIATIONS[$language] ?? []);
            $words = implode('|', array_map(fn($m) => preg_quote($m, '/'), $markers));
            $suffixes = array_map(fn($m) => preg_quote($m, '/'), self::SUFFIXES[$language] ?? []);

            // Not \b: "c/" ends in a slash, and \b only sees a word edge after a letter or digit.
            $pattern = '(?<![\p{L}\d])(?:' . $words . ')(?![\p{L}\d])';
            if ($suffixes) {
                $pattern .= '|(?:' . implode('|', $suffixes) . ')\b';
            }

            self::$patternCache[$language] = '/' . $pattern . '/iu';
        }

        return self::$patternCache[$language];
    }

    private static function abbreviationPattern(string $language): ?string
    {
        if (!array_key_exists($language, self::$abbreviationCache)) {
            $abbreviations = self::NAME_ABBREVIATIONS[$language] ?? [];
            self::$abbreviationCache[$language] = $abbreviations === []
                ? null
                : '/(?<![\p{L}\d])(?:' . implode('|', array_map(fn($m) => preg_quote($m, '/'), $abbreviations)) . ')(?![\p{L}\d])/iu';
        }

        return self::$abbreviationCache[$language];
    }
}
