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

    private function parse(string $fullName): void
    {
        $fullName = trim($fullName);

        if (empty($fullName)) {
            throw new \InvalidArgumentException("Full name cannot be empty");
        }

        $parts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) < 2) {
            $this->firstNames = [$fullName];
            $this->lastNames = [];
            return;
        }

        $lastNameParticles = ['de', 'del', 'di', 'da', 'van', 'von', 'le', 'la', 'los', 'las', 'y', 'e'];

        $splitIndex = (int) floor(count($parts) / 2);

        $foundLastNameParticle = false;
        for ($i = 1; $i < count($parts); $i++) {
            if (in_array(strtolower($parts[$i]), $lastNameParticles)) {
                $splitIndex = $i;
                $foundLastNameParticle = true;
                break;
            }
        }

        $this->firstNames = array_slice($parts, 0, $splitIndex);
        $this->lastNames = array_slice($parts, $splitIndex);
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
