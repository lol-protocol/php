<?php

namespace DefamatoryContentReview;

class DefamatoryContentReviewer
{
    private WordList $wordList;
    private array $highSeverityRiskTypes = [
        'ordinario',
        'moral',
        'discapacidad',
        'genero',
        'religioso',
        'etnico',
    ];
    private array $mediumSeverityRiskTypes = [
        'animal',
        'intelectual',
        'fisico',
    ];
    private string $language = 'es';
    private array $riskCategories = [];

    public function __construct(WordList $wordList, string $language = 'es', array $riskCategories = [])
    {
        $this->wordList = $wordList;
        $this->language = $language;
        $this->riskCategories = $riskCategories;
    }

    public function validateFullName(string $firstName, string $lastName): ValidationResult
    {
        $fullName = trim($firstName . ' ' . $lastName);
        $result = new ValidationResult($fullName, true, $this->language);

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
            $riskType = $match['riskType'] ?? 'ordinario';
            $severity = $match['severity'] ?? 'medium';

            $result->addFlaggedTerm($match['found'], $match['category'], $riskType, $severity);

            if (in_array($riskType, $this->highSeverityRiskTypes)) {
                $maxSeverity = 'high';
            } elseif (in_array($riskType, $this->mediumSeverityRiskTypes) && $maxSeverity !== 'high') {
                $maxSeverity = 'medium';
            }
        }

        $result->setSeverity($maxSeverity);
        return $result;
    }

    public function validateName(string $name): ValidationResult
    {
        $result = new ValidationResult($name, true, $this->language);
        $matches = $this->wordList->findInText($name);

        if (count($matches) === 0) {
            $result->setValid(true);
            $result->setSeverity('none');
            return $result;
        }

        $result->setValid(false);
        $maxSeverity = 'low';

        foreach ($matches as $match) {
            $riskType = $match['riskType'] ?? 'ordinario';
            $severity = $match['severity'] ?? 'medium';

            $result->addFlaggedTerm($match['found'], $match['category'], $riskType, $severity);

            if (in_array($riskType, $this->highSeverityRiskTypes)) {
                $maxSeverity = 'high';
            } elseif (in_array($riskType, $this->mediumSeverityRiskTypes) && $maxSeverity !== 'high') {
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
        $data = $result->toArray();

        return [
            'name' => $data['fullName'],
            'language' => $data['language'],
            'valid' => $data['isValid'],
            'severity' => $data['severity'],
            'flaggedTermsCount' => $data['totalFlagged'],
            'flaggedCategories' => $data['flaggedCategories'],
            'flaggedRiskTypes' => $data['flaggedRiskTypes'],
            'flaggedTerms' => $data['flaggedTerms'],
            'termsByRiskType' => $data['termsByRiskType'],
            'recommendation' => $this->getRecommendation($data['severity'], $data['flaggedRiskTypes']),
            'riskAnalysis' => $this->analyzeRisks($data['flaggedRiskTypes']),
        ];
    }

    private function getRecommendation(string $severity, array $riskTypes): string
    {
        return match ($severity) {
            'high' => 'Rechazar: Contiene insultos graves o palabras inapropiadas. Tipos de riesgo: ' . implode(', ', $riskTypes),
            'medium' => 'Revisar: Contiene términos potencialmente ofensivos. Tipos de riesgo: ' . implode(', ', $riskTypes),
            'low' => 'Advertencia: Contiene palabras que podrían ser consideradas inapropiadas.',
            default => 'Aceptar: No contiene contenido difamatorio detectado.',
        };
    }

    private function analyzeRisks(array $riskTypes): array
    {
        $analysis = [];

        $riskTypeDescriptions = [
            'animal' => 'Comparaciones animales',
            'intelectual' => 'Insultos intelectuales',
            'discapacidad' => 'Insultos sobre discapacidad',
            'fisico' => 'Insultos sobre apariencia física',
            'moral' => 'Insultos morales',
            'genero' => 'Insultos de género/sexualidad',
            'ordinario' => 'Palabras vulgares',
            'burlesco' => 'Burlas/ridiculización',
            'etnico' => 'Insultos étnicos',
            'religioso' => 'Insultos religiosos',
        ];

        foreach ($riskTypes as $riskType) {
            $isSevere = in_array($riskType, $this->highSeverityRiskTypes);
            $analysis[$riskType] = [
                'description' => $riskTypeDescriptions[$riskType] ?? $riskType,
                'isSevere' => $isSevere,
                'level' => $isSevere ? 'high' : 'medium',
            ];
        }

        return $analysis;
    }

    public function setHighSeverityRiskTypes(array $riskTypes): self
    {
        $this->highSeverityRiskTypes = $riskTypes;
        return $this;
    }

    public function setMediumSeverityRiskTypes(array $riskTypes): self
    {
        $this->mediumSeverityRiskTypes = $riskTypes;
        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function loadLanguage(string $languageCode): bool
    {
        $languageFile = __DIR__ . '/../../config/languages/' . $languageCode . '.php';

        if (!file_exists($languageFile)) {
            return false;
        }

        $config = require $languageFile;
        $this->wordList = new WordList($config, $languageCode, $this->riskCategories);
        $this->language = $languageCode;

        return true;
    }

    public function getWordListStatistics(): array
    {
        return $this->wordList->getStatistics();
    }
}
