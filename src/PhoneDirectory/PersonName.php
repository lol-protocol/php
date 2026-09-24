<?php

namespace PhoneDirectory;

class PersonName
{
    public const PARTICLES = ['de', 'del', 'della', 'di', 'da', 'das', 'do', 'dos', 'du', 'van', 'von', 'der', 'den', 'le', 'la', 'los', 'las'];

    // Single-letter connectors collide with middle initials ("John E Smith"), so they only count when the language uses them.
    private const LANGUAGE_CONNECTORS = [
        'es' => ['y'],
        'pt' => ['e'],
        'it' => ['e'],
    ];

    public const LANGUAGE_SURNAME_COUNT = [
        'es' => 2,
        'pt' => 2,
    ];

    // Honorifics stripped from the front of the given name so they don't get parsed as part of it.
    // Bare "M" (French Monsieur) is deliberately excluded: it is indistinguishable from a middle initial.
    private const TITLES = ['mr', 'mrs', 'ms', 'miss', 'dr', 'dra', 'rev', 'capt', 'sr', 'sra', 'srta', 'sta', 'mme', 'mlle', 'herr', 'frau', 'wwe', 'witwe', 'sig', 'sigra'];

    // Two-word "widow of [husband]" markers. The words after the phrase are the husband's name, not the
    // woman's given name; this project doesn't attempt to parse that further, but records that the entry
    // is a widow's rather than silently reading "Vda." itself as a first name.
    private const WIDOW_PHRASES = [
        ['wid', 'of'], ['widow', 'of'],
        ['vda', 'de'], ['viuda', 'de'],
        ['vve', 'de'], ['veuve', 'de'],
        ['ved', 'di'], ['vedova', 'di'],
        ['vva', 'de'], ['viuva', 'de'],
    ];

    private array $firstNames;
    private array $lastNames;
    private ?string $language;
    private ?string $title = null;

    public function __construct(string $fullName, ?string $language = null)
    {
        $this->firstNames = [];
        $this->lastNames = [];
        $this->language = $language !== null ? strtolower($language) : null;
        $this->parse($fullName);
    }

    private function isParticle(string $word): bool
    {
        $lower = mb_strtolower($word, 'UTF-8');

        return in_array($lower, self::PARTICLES, true)
            || in_array($lower, self::LANGUAGE_CONNECTORS[$this->language] ?? [], true);
    }

    private function normalizeCase(string $word, bool $isLeadingToken = false): string
    {
        if (!$isLeadingToken && $this->isParticle($word)) {
            return mb_strtolower($word, 'UTF-8');
        }

        return mb_convert_case(mb_strtolower($word, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function splitWords(string $text): array
    {
        return preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    }

    private function normalizeTitleToken(string $word): string
    {
        return mb_strtolower(rtrim($word, '.'), 'UTF-8');
    }

    /** Removes a recognized honorific or widow phrase from the front of $words, recording it as the title. */
    private function extractTitle(array $words): array
    {
        if (count($words) >= 2 && in_array(
            [$this->normalizeTitleToken($words[0]), $this->normalizeTitleToken($words[1])],
            self::WIDOW_PHRASES,
            true
        )) {
            $this->title = $this->formatTitle(array_slice($words, 0, 2));
            return array_slice($words, 2);
        }

        if ($words !== [] && in_array($this->normalizeTitleToken($words[0]), self::TITLES, true)) {
            $this->title = $this->formatTitle([$words[0]]);
            return array_slice($words, 1);
        }

        return $words;
    }

    /** Title Case for the first word, lowercase for the rest ("Vda. de", not "Vda. De"). */
    private function formatTitle(array $words): string
    {
        $formatted = [mb_convert_case(mb_strtolower($words[0], 'UTF-8'), MB_CASE_TITLE, 'UTF-8')];
        for ($i = 1; $i < count($words); $i++) {
            $formatted[] = mb_strtolower($words[$i], 'UTF-8');
        }

        return implode(' ', $formatted);
    }

    private function parse(string $fullName): void
    {
        $fullName = trim($fullName);

        if ($fullName === '') {
            throw new \InvalidArgumentException("Full name cannot be empty");
        }

        if (str_contains($fullName, ',')) {
            [$lastNamePart, $firstNamePart] = explode(',', $fullName, 2);
            $this->lastNames = array_map(fn($w) => $this->normalizeCase($w), $this->splitWords($lastNamePart));
            $firstNameWords = $this->extractTitle($this->splitWords($firstNamePart));
            $this->firstNames = array_map(
                fn($w, $i) => $this->normalizeCase($w, $i === 0),
                $firstNameWords,
                array_keys($firstNameWords)
            );
            return;
        }

        $words = $this->extractTitle($this->splitWords($fullName));
        $parts = array_map(fn($w, $i) => $this->normalizeCase($w, $i === 0), $words, array_keys($words));

        // Walk backwards taking one surname per iteration, pulling in any particles that precede it
        // ("de la Cruz", "van der Rohe", "Ortega y Gasset"); the first token always stays a given name.
        $surnameCount = self::LANGUAGE_SURNAME_COUNT[$this->language] ?? 1;
        $lastNames = [];
        $taken = 0;

        while ($taken < $surnameCount && count($parts) > 1) {
            $group = [array_pop($parts)];
            while (count($parts) > 1 && $this->isParticle(end($parts))) {
                array_unshift($group, array_pop($parts));
            }
            $lastNames = array_merge($group, $lastNames);
            $taken++;
        }

        $this->firstNames = $parts;
        $this->lastNames = $lastNames;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    /** The honorific or widow phrase stripped from the given name ("Mrs.", "Vda. de"); null when none was found. */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getFirstNames(): array
    {
        return $this->firstNames;
    }

    public function getFirstName(): string
    {
        return $this->firstNames[0] ?? '';
    }

    public function getMiddleNames(): array
    {
        return array_slice($this->firstNames, 1);
    }

    public function getLastNames(): array
    {
        return $this->lastNames;
    }

    /** First surname word that is not a particle ("de la Cruz" → "Cruz"); null when there is no surname. */
    public function getSurnameRoot(): ?string
    {
        foreach ($this->lastNames as $word) {
            if (!$this->isParticle($word)) {
                return $word;
            }
        }

        return null;
    }

    public function getPrimaryLastName(): string
    {
        return $this->lastNames[0] ?? '';
    }

    public function getFullName(): string
    {
        $all = array_merge($this->firstNames, $this->lastNames);
        return implode(' ', $all);
    }

    public function getFormattedName(): string
    {
        $lastNameStr = implode(' ', $this->lastNames);
        $firstNameStr = implode(' ', $this->firstNames);

        if (empty($lastNameStr)) {
            return $firstNameStr;
        }

        return "{$lastNameStr}, {$firstNameStr}";
    }

    public function toArray(): array
    {
        return [
            'fullName' => $this->getFullName(),
            'formattedName' => $this->getFormattedName(),
            'firstName' => $this->getFirstName(),
            'firstNames' => $this->firstNames,
            'middleNames' => $this->getMiddleNames(),
            'lastNames' => $this->lastNames,
            'primaryLastName' => $this->getPrimaryLastName(),
            'title' => $this->title,
        ];
    }
}
