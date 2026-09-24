<?php

namespace PhoneDirectory;

use DefamatoryContentReview\AccentFolding;

/**
 * Proposes which entries in different directories are probably the same person.
 *
 * Candidates share a surname sound (Soundex), a country, and come from different source directories.
 * Given names must not contradict each other; everything else adds evidence to a 0-1 score. A different
 * street lowers the score but does not rule a link out, since families moved between editions.
 */
final class RecordLinker
{
    private const WEIGHT_SAME_SURNAME = 0.3;
    private const WEIGHT_SIMILAR_SURNAME = 0.15;
    private const WEIGHT_SAME_GIVEN_NAME = 0.3;
    private const WEIGHT_MATCHING_INITIAL = 0.15;
    private const WEIGHT_SAME_STREET = 0.25;
    private const WEIGHT_SAME_PHONE = 0.15;

    private const STREET_ABBREVIATIONS = [
        'street' => 'st', 'avenue' => 'ave', 'road' => 'rd', 'drive' => 'dr', 'lane' => 'ln',
        'boulevard' => 'blvd', 'circle' => 'cir', 'avenida' => 'av', 'calle' => 'c',
    ];

    private PhoneDirectoryCatalog $catalog;
    private float $minimumScore;

    public function __construct(?PhoneDirectoryCatalog $catalog = null, float $minimumScore = 0.5)
    {
        $this->catalog = $catalog ?? new PhoneDirectoryCatalog();
        $this->minimumScore = $minimumScore;
    }

    /**
     * @param PhoneDirectoryEntry[] $entries entries from two or more source directories
     * @return RecordLink[] highest score first
     */
    public function link(array $entries): array
    {
        $blocks = [];
        foreach ($entries as $entry) {
            $surnameKey = SurnameKeys::soundex($entry->getPersonName()->getSurnameRoot());
            $givenName = $this->normalize($entry->getFirstName());
            if ($surnameKey === null || $givenName === '' || $entry->getSourceDirectoryId() === null) {
                continue;
            }

            // compare() only ever links entries whose given names share a first letter (either they
            // match outright, or one is an initial matching the other's first letter), so blocking on
            // that letter too splits a common-surname block into much smaller pieces without discarding
            // any pair that could have linked.
            $blocks[$entry->getCountryCode() . ':' . $surnameKey . ':' . $givenName[0]][] = $entry;
        }

        $links = [];
        foreach ($blocks as $block) {
            $count = count($block);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $link = $this->compare($block[$i], $block[$j]);
                    if ($link !== null && $link->score >= $this->minimumScore) {
                        $links[] = $link;
                    }
                }
            }
        }

        usort($links, fn(RecordLink $a, RecordLink $b) => $b->score <=> $a->score);

        return $links;
    }

    private function compare(PhoneDirectoryEntry $a, PhoneDirectoryEntry $b): ?RecordLink
    {
        if ($a->getSourceDirectoryId() === $b->getSourceDirectoryId()) {
            return null;
        }

        $givenA = $this->normalize($a->getFirstName());
        $givenB = $this->normalize($b->getFirstName());
        if ($givenA === '' || $givenB === '') {
            return null;
        }

        $score = 0.0;
        $evidence = [];

        if ($givenA === $givenB) {
            $score += self::WEIGHT_SAME_GIVEN_NAME;
            $evidence[] = 'same given name';
        } elseif ((strlen($givenA) === 1 || strlen($givenB) === 1) && $givenA[0] === $givenB[0]) {
            $score += self::WEIGHT_MATCHING_INITIAL;
            $evidence[] = 'matching initial';
        } else {
            return null;
        }

        if ($this->normalize($a->getPersonName()->getSurnameRoot()) === $this->normalize($b->getPersonName()->getSurnameRoot())) {
            $score += self::WEIGHT_SAME_SURNAME;
            $evidence[] = 'same surname';
        } else {
            $score += self::WEIGHT_SIMILAR_SURNAME;
            $evidence[] = 'similar-sounding surname';
        }

        if ($this->normalizeStreet($a->getStreet()) === $this->normalizeStreet($b->getStreet())) {
            $score += self::WEIGHT_SAME_STREET;
            $evidence[] = 'same address';
        }

        $phoneA = preg_replace('/\D/', '', $a->getPhoneNumber() ?? '');
        if ($phoneA !== '' && $phoneA === preg_replace('/\D/', '', $b->getPhoneNumber() ?? '')) {
            $score += self::WEIGHT_SAME_PHONE;
            $evidence[] = 'same phone number';
        }

        [$earlier, $later] = $this->year($a) <= $this->year($b) ? [$a, $b] : [$b, $a];

        return new RecordLink($earlier, $later, round($score, 2), $evidence);
    }

    private function year(PhoneDirectoryEntry $entry): int
    {
        $dirId = $entry->getSourceDirectoryId();

        $catalogYear = $this->catalog->get($dirId)['year'] ?? null;
        if ($catalogYear !== null) {
            return $catalogYear;
        }

        // A directory id outside the built-in catalog often still embeds a year, following the
        // catalog's own "<country>_<year>_<place>" convention (e.g. "custom_1990_springfield");
        // use it so links between non-catalog directories are still ordered chronologically.
        // Not \b: an underscore is a "word" character too, so "custom_1990_x" has no word boundary
        // around the digits at all; look for a 4-digit run not itself touching other digits instead.
        if ($dirId && preg_match('/(?<!\d)(1[89]\d{2}|20\d{2})(?!\d)/', $dirId, $m)) {
            return (int) $m[1];
        }

        // Genuinely unknown: fall back to whichever entry was given to link() first, rather than
        // claiming a chronology we don't have.
        return PHP_INT_MAX;
    }

    private function normalize(?string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', AccentFolding::fold(mb_strtolower($text ?? '', 'UTF-8')));
    }

    private function normalizeStreet(string $street): string
    {
        $words = preg_split('/[^a-z0-9]+/', AccentFolding::fold(mb_strtolower($street, 'UTF-8')), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', array_map(fn($w) => self::STREET_ABBREVIATIONS[$w] ?? $w, $words));
    }
}
