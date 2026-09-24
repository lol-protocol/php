<?php

namespace PhoneDirectory;

use DefamatoryContentReview\AccentFolding;
use DefamatoryContentReview\PhoneticFolderRegistry;

/**
 * Phonetic keys for finding spelling variants of a surname.
 *
 * Soundex is language-neutral and catches near spellings (Smith/Smyth, Schmidt/Schmitt) but keeps the
 * first letter, so it misses Valdez/Baldez. The language key uses the project's per-language phonetic
 * folders, which unify real spelling equivalences (b/v, z/s, ll/y, ü/ue) but not near misses. A search
 * matches on either.
 */
final class SurnameKeys
{
    private const FOLDER_LANGUAGES = [
        'es' => 'spa',
        'pt' => 'por',
        'it' => 'ita',
        'fr' => 'fra',
        'de' => 'deu',
        'nl' => 'nld',
    ];

    private const MAX_SURNAME_LENGTH = 1000;

    public static function soundex(?string $surname): ?string
    {
        if ($surname === null || strlen($surname) === 0) {
            return null;
        }

        $truncated = substr($surname, 0, self::MAX_SURNAME_LENGTH);
        $letters = preg_replace('/[^a-z]/', '', AccentFolding::fold(mb_strtolower($truncated, 'UTF-8')));

        return $letters === '' ? null : soundex($letters);
    }

    public static function languageKey(?string $surname, ?string $language): ?string
    {
        $folderLanguage = self::FOLDER_LANGUAGES[$language] ?? null;
        if ($folderLanguage === null || $surname === null || trim($surname) === '') {
            return null;
        }

        $truncated = substr($surname, 0, self::MAX_SURNAME_LENGTH);
        return PhoneticFolderRegistry::fold($folderLanguage, $truncated);
    }

    /** The word a surname is indexed by: "de la Cruz" → "Cruz", "García López" → "García". */
    public static function root(string $surname): ?string
    {
        foreach (preg_split('/\s+/u', trim($surname), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (!in_array(mb_strtolower($word, 'UTF-8'), PersonName::PARTICLES, true)) {
                return $word;
            }
        }

        return null;
    }
}
