<?php

namespace PhoneDirectory;

class PersonName
{
    private const PARTICLES = ['de', 'del', 'della', 'di', 'da', 'das', 'do', 'dos', 'du', 'van', 'von', 'der', 'den', 'le', 'la', 'los', 'las'];

    // Single-letter connectors collide with middle initials ("John E Smith"), so they only count when the language uses them.
    private const LANGUAGE_CONNECTORS = [
        'es' => ['y'],
        'pt' => ['e'],
        'it' => ['e'],
    ];

    private const LANGUAGE_SURNAME_COUNT = [
        'es' => 2,
        'pt' => 2,
    ];

    private array $firstNames;
    private array $lastNames;
    private ?string $language;

    public function __construct(string $fullName, ?string $language = null)
    {
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

    private function parse(string $fullName): void
    {
        $fullName = trim($fullName);

        if ($fullName === '') {
            throw new \InvalidArgumentException("Full name cannot be empty");
        }

        if (str_contains($fullName, ',')) {
            [$lastNamePart, $firstNamePart] = explode(',', $fullName, 2);
            $this->lastNames = array_map(fn($w) => $this->normalizeCase($w), $this->splitWords($lastNamePart));
            $firstNameWords = $this->splitWords($firstNamePart);
            $this->firstNames = array_map(
                fn($w, $i) => $this->normalizeCase($w, $i === 0),
                $firstNameWords,
                array_keys($firstNameWords)
            );
            return;
        }

        $words = $this->splitWords($fullName);
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
        ];
    }
}
