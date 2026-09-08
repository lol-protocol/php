<?php

namespace DefamatoryContentReview;

class ValidationResult
{
    private bool $isValid;
    private array $flaggedTerms = [];
    private array $flaggedCategories = [];
    private string $severity = 'none';
    private string $fullName;

    public function __construct(string $fullName, bool $isValid = true)
    {
        $this->fullName = $fullName;
        $this->isValid = $isValid;
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

    public function addFlaggedTerm(string $term, string $category): self
    {
        $this->flaggedTerms[] = [
            'term' => $term,
            'category' => $category,
        ];

        if (!in_array($category, $this->flaggedCategories)) {
            $this->flaggedCategories[] = $category;
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

    public function toArray(): array
    {
        return [
            'fullName' => $this->fullName,
            'isValid' => $this->isValid,
            'severity' => $this->severity,
            'flaggedTerms' => $this->flaggedTerms,
            'flaggedCategories' => $this->flaggedCategories,
            'totalFlagged' => count($this->flaggedTerms),
        ];
    }
}
