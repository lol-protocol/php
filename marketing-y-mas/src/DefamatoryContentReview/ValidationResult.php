<?php

namespace DefamatoryContentReview;

/**
 * Resultado de validar un nombre: validez, severidad, puntaje y los
 * términos marcados. Los términos y sus consultas viven en
 * `FlaggedTermCollection` (colaborador interno) — esta clase expone los
 * mismos métodos de siempre, delegando ahí.
 */
class ValidationResult
{
    private string $fullName;
    private string $language;
    private bool $isValid;
    private string $severity = 'none';
    private float $score = 0.0;
    private FlaggedTermCollection $terms;
    /** @var array<string,float> idiomas consultados => afinidad con el principal */
    private array $languagesChecked = [];

    public function __construct(string $fullName, bool $isValid = true, string $language = 'spa')
    {
        $this->fullName = $fullName;
        $this->isValid = $isValid;
        $this->language = $language;
        $this->languagesChecked = [$language => 1.0];
        $this->terms = new FlaggedTermCollection($language);
    }

    public function isValid(): bool { return $this->isValid; }
    public function setValid(bool $valid): self { $this->isValid = $valid; return $this; }

    public function addFlaggedTerm(array $term): self { $this->terms->add($term); return $this; }

    public function getFlaggedTerms(): array { return $this->terms->all(); }
    public function getFlaggedCategories(): array { return $this->terms->categories(); }
    public function getFlaggedRiskTypes(): array { return $this->terms->riskTypes(); }
    public function getTermsByRiskType(string $riskType): array { return $this->terms->byRiskType($riskType); }
    public function getTermsByLanguage(string $language): array { return $this->terms->byLanguage($language); }

    /** Coincidencias halladas en el idioma principal, no en los asociados. */
    public function getPrimaryLanguageTerms(): array { return $this->getTermsByLanguage($this->language); }

    public function hasNameCollision(): bool { return $this->terms->hasNameCollision(); }
    public function getNameCollisionTerms(): array { return $this->terms->nameCollisionTerms(); }
    public function getTermsByDetectionMethod(string $method): array { return $this->terms->byDetectionMethod($method); }
    public function getPhoneticFusionTerms(): array { return $this->getTermsByDetectionMethod('phonetic_fusion'); }
    public function getPhoneticVariantTerms(): array { return $this->getTermsByDetectionMethod('phonetic_variant'); }
    public function hasOnlyPhoneticDetections(): bool { return $this->terms->hasOnlyPhoneticDetections(); }

    public function setSeverity(string $severity): self { $this->severity = $severity; return $this; }
    public function getSeverity(): string { return $this->severity; }

    /** Puntaje crudo antes de discretizar en severidad (ver ScoringPolicy) — el peso del peor término, por defecto. */
    public function setScore(float $score): self { $this->score = $score; return $this; }
    public function getScore(): float { return $this->score; }

    public function getFullName(): string { return $this->fullName; }
    public function getLanguage(): string { return $this->language; }

    public function setLanguagesChecked(array $languages): self { $this->languagesChecked = $languages; return $this; }
    /** @return array<string,float> */
    public function getLanguagesChecked(): array { return $this->languagesChecked; }

    public function toArray(): array
    {
        $termsByRiskType = [];
        foreach ($this->getFlaggedRiskTypes() as $riskType) {
            $termsByRiskType[$riskType] = $this->getTermsByRiskType($riskType);
        }

        return [
            'fullName' => $this->fullName,
            'language' => $this->language,
            'languagesChecked' => $this->languagesChecked,
            'isValid' => $this->isValid,
            'severity' => $this->severity,
            'score' => $this->score,
            'flaggedTerms' => $this->getFlaggedTerms(),
            'flaggedCategories' => $this->getFlaggedCategories(),
            'flaggedRiskTypes' => $this->getFlaggedRiskTypes(),
            'termsByRiskType' => $termsByRiskType,
            'hasNameCollision' => $this->hasNameCollision(),
            'hasOnlyPhoneticDetections' => $this->hasOnlyPhoneticDetections(),
            'totalFlagged' => count($this->getFlaggedTerms()),
        ];
    }
}
