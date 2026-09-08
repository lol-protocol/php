<?php

namespace DefamatoryContentReview;

class DefamatoryContentReviewer
{
    private WordList $wordList;
    private array $highSeverityCategories = [
        'insultos_morales',
        'palabras_soeces',
    ];
    private array $mediumSeverityCategories = [
        'insultos_personales',
        'insultos_corporales',
        'insultos_comportamiento',
    ];

    public function __construct(WordList $wordList)
    {
        $this->wordList = $wordList;
    }

    public function validateFullName(string $firstName, string $lastName): ValidationResult
    {
        $fullName = trim($firstName . ' ' . $lastName);
        $result = new ValidationResult($fullName);

        $firstNameMatches = $this->wordList->findInText($firstName);
        $lastNameMatches = $this->wordList->findInText($lastName);

        $allMatches = array_merge($firstNameMatches, $lastNameMatches);

        if (count($allMatches) === 0) {
            $result->setValid(true);
            $result->setSeverity('none');
            return $result;
        }

        $result->setValid(false);

        $maxSeverity = 'low';

        foreach ($allMatches as $match) {
            $result->addFlaggedTerm($match['found'], $match['category']);

            if (in_array($match['category'], $this->highSeverityCategories)) {
                $maxSeverity = 'high';
            } elseif (in_array($match['category'], $this->mediumSeverityCategories)
                     && $maxSeverity !== 'high') {
                $maxSeverity = 'medium';
            }
        }

        $result->setSeverity($maxSeverity);

        return $result;
    }

    public function validateName(string $name): ValidationResult
    {
        $result = new ValidationResult($name);
        $matches = $this->wordList->findInText($name);

        if (count($matches) === 0) {
            $result->setValid(true);
            $result->setSeverity('none');
            return $result;
        }

        $result->setValid(false);
        $maxSeverity = 'low';

        foreach ($matches as $match) {
            $result->addFlaggedTerm($match['found'], $match['category']);

            if (in_array($match['category'], $this->highSeverityCategories)) {
                $maxSeverity = 'high';
            } elseif (in_array($match['category'], $this->mediumSeverityCategories)
                     && $maxSeverity !== 'high') {
                $maxSeverity = 'medium';
            }
        }

        $result->setSeverity($maxSeverity);

        return $result;
    }

    public function batchValidateNames(array $names): array
    {
        $results = [];

        foreach ($names as $name) {
            $results[] = $this->validateName($name);
        }

        return $results;
    }

    public function batchValidateFullNames(array $fullNames): array
    {
        $results = [];

        foreach ($fullNames as $name) {
            $parts = explode(' ', trim($name), 2);
            $firstName = $parts[0] ?? '';
            $lastName = $parts[1] ?? '';

            $results[] = $this->validateFullName($firstName, $lastName);
        }

        return $results;
    }

    public function getDetailedReport(ValidationResult $result): array
    {
        return [
            'name' => $result->getFullName(),
            'valid' => $result->isValid(),
            'severity' => $result->getSeverity(),
            'flaggedTermsCount' => count($result->getFlaggedTerms()),
            'flaggedCategories' => $result->getFlaggedCategories(),
            'flaggedTerms' => $result->getFlaggedTerms(),
            'recommendation' => $this->getRecommendation($result->getSeverity()),
        ];
    }

    private function getRecommendation(string $severity): string
    {
        return match ($severity) {
            'high' => 'Rechazar: Contiene insultos graves o palabras inapropiadas.',
            'medium' => 'Revisar: Contiene términos potencialmente ofensivos.',
            'low' => 'Advertencia: Contiene palabras que podrían ser consideradas inapropiadas.',
            default => 'Aceptar: No contiene contenido difamatorio detectado.',
        };
    }

    public function setHighSeverityCategories(array $categories): self
    {
        $this->highSeverityCategories = $categories;
        return $this;
    }

    public function setMediumSeverityCategories(array $categories): self
    {
        $this->mediumSeverityCategories = $categories;
        return $this;
    }
}
