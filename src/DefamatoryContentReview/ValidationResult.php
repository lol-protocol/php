<?php

namespace DefamatoryContentReview;

class ValidationResult
{
    private bool $isValid;
    private array $flaggedTerms = [];
    private array $flaggedCategories = [];
    private array $flaggedRiskTypes = [];
    private string $severity = 'none';
    private string $fullName;
    private string $language = 'es';

    public function __construct(string $fullName, bool $isValid = true, string $language = 'es')
    {
        $this->fullName = $fullName;
        $this->isValid = $isValid;
        $this->language = $language;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function setValid(bool $valid): self
    {
        $this->isValid = $valid;
        return $this;
    }

    public function addFlaggedTerm(string $term, string $category, string $riskType = 'ordinario', string $severity = 'medium'): self
    {
        $this->flaggedTerms[] = [
            'term' => $term,
            'category' => $category,
            'riskType' => $riskType,
            'severity' => $severity,
        ];

        if (!in_array($category, $this->flaggedCategories)) {
            $this->flaggedCategories[] = $category;
        }

        if (!in_array($riskType, $this->flaggedRiskTypes)) {
            $this->flaggedRiskTypes[] = $riskType;
        }

        return $this;
    }

    public function getFlaggedTerms(): array
    {
        return $this->flaggedTerms;
    }

    public function getFlaggedCategories(): array
    {
        return $this->flaggedCategories;
    }

    public function getFlaggedRiskTypes(): array
    {
        return $this->flaggedRiskTypes;
    }

    public function getTermsByRiskType(string $riskType): array
    {
        return array_filter($this->flaggedTerms, fn($term) => $term['riskType'] === $riskType);
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function toArray(): array
    {
        $termsByRiskType = [];
        foreach ($this->flaggedRiskTypes as $riskType) {
            $termsByRiskType[$riskType] = $this->getTermsByRiskType($riskType);
        }

        return [
            'fullName' => $this->fullName,
            'language' => $this->language,
            'isValid' => $this->isValid,
            'severity' => $this->severity,
            'flaggedTerms' => $this->flaggedTerms,
            'flaggedCategories' => $this->flaggedCategories,
            'flaggedRiskTypes' => $this->flaggedRiskTypes,
            'termsByRiskType' => $termsByRiskType,
            'totalFlagged' => count($this->flaggedTerms),
        ];
    }
}
