<?php

namespace PhoneDirectory;

class PersonName
{
    private array $firstNames;
    private array $lastNames;

    public function __construct(string $fullName)
    {
        $this->parse($fullName);
    }

    private function normalizeCase(string $word): string
    {
        $lowerWord = strtolower($word);
        $particles = ['de', 'del', 'di', 'da', 'van', 'von', 'le', 'la', 'los', 'las', 'y', 'e'];

        // Keep particles lowercase (traditional naming convention)
        if (in_array($lowerWord, $particles)) {
            return $lowerWord;
        }

        // Capitalize first letter for regular names
        return ucfirst($lowerWord);
    }

    private function parse(string $fullName): void
    {
        $fullName = trim($fullName);

        if (empty($fullName)) {
            throw new \InvalidArgumentException("Full name cannot be empty");
        }

        // Handle comma-separated format: "LastName, FirstName" or "LastName1 LastName2, FirstName"
        if (strpos($fullName, ',') !== false) {
            [$lastNamePart, $firstNamePart] = explode(',', $fullName, 2);
            $lastNameParts = preg_split('/\s+/', trim($lastNamePart), -1, PREG_SPLIT_NO_EMPTY);
            $firstNameParts = preg_split('/\s+/', trim($firstNamePart), -1, PREG_SPLIT_NO_EMPTY);
            $this->lastNames = array_map(function($name) { return $this->normalizeCase($name); }, $lastNameParts);
            $this->firstNames = array_map(function($name) { return $this->normalizeCase($name); }, $firstNameParts);
            return;
        }

        $parts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);
        // Normalize case for all parts
        $parts = array_map(function($name) { return $this->normalizeCase($name); }, $parts);

        if (count($parts) < 2) {
            $this->firstNames = [$fullName];
            $this->lastNames = [];
            return;
        }

        $lastNameParticles = ['de', 'del', 'di', 'da', 'van', 'von', 'le', 'la', 'los', 'las', 'y', 'e'];

        // Check for name particles (de, del, di, da, van, von, etc.)
        $splitIndex = null;
        for ($i = 1; $i < count($parts); $i++) {
            if (in_array(strtolower($parts[$i]), $lastNameParticles)) {
                $splitIndex = $i;
                break;
            }
        }

        if ($splitIndex !== null) {
            // Particle found, split at particle
            $this->firstNames = array_slice($parts, 0, $splitIndex);
            $this->lastNames = array_slice($parts, $splitIndex);
        } else {
            // No particle found - use different strategies based on count
            if (count($parts) <= 3) {
                // For 2-3 parts: last word is last name, rest are first/middle names
                $this->firstNames = array_slice($parts, 0, -1);
                $this->lastNames = [array_pop($parts)];
            } else {
                // For 4+ parts: use midpoint (handles compound surnames like "García López")
                $splitIndex = (int) floor(count($parts) / 2);
                $this->firstNames = array_slice($parts, 0, $splitIndex);
                $this->lastNames = array_slice($parts, $splitIndex);
            }
        }
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
