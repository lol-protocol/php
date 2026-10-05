<?php

namespace DefamatoryContentReview;

/**
 * Resultado de validar un nombre: validez, severidad, puntaje y los
 * términos marcados. Los términos y sus consultas viven en
 * `FlaggedTermCollection` (colaborador interno) — esta clase expone los
 * mismos métodos de siempre, delegando ahí.
 *
 * @phpstan-import-type FlaggedEntry from FlaggedTermCollection
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

    /** @param array<string,mixed> $term */ public function addFlaggedTerm(array $term): self { $this->terms->add($term); return $this; }

    /** @return array<int,FlaggedEntry> */ public function getFlaggedTerms(): array { return $this->terms->all(); }
    /** @return array<int,string> */ public function getFlaggedCategories(): array { return $this->terms->categories(); }
    /** @return array<int,string> */ public function getFlaggedRiskTypes(): array { return $this->terms->riskTypes(); }
    /** @return array<int,FlaggedEntry> */ public function getTermsByRiskType(string $riskType): array { return $this->terms->byRiskType($riskType); }
    /** @return array<int,FlaggedEntry> */ public function getTermsByLanguage(string $language): array { return $this->terms->byLanguage($language); }

    /** @return array<int,FlaggedEntry> Coincidencias halladas en el idioma principal, no en los asociados. */
    public function getPrimaryLanguageTerms(): array { return $this->getTermsByLanguage($this->language); }

    public function hasNameCollision(): bool { return $this->terms->hasNameCollision(); }
    /** @return array<int,FlaggedEntry> */ public function getNameCollisionTerms(): array { return $this->terms->nameCollisionTerms(); }
    /** @return array<int,FlaggedEntry> */ public function getTermsByDetectionMethod(string $method): array { return $this->terms->byDetectionMethod($method); }
    /** @return array<int,FlaggedEntry> */ public function getPhoneticFusionTerms(): array { return $this->getTermsByDetectionMethod('phonetic_fusion'); }
    /** @return array<int,FlaggedEntry> */ public function getPhoneticVariantTerms(): array { return $this->getTermsByDetectionMethod('phonetic_variant'); }
    public function hasOnlyPhoneticDetections(): bool { return $this->terms->hasOnlyPhoneticDetections(); }

    public function setSeverity(string $severity): self { $this->severity = $severity; return $this; }
    public function getSeverity(): string { return $this->severity; }

    /** Puntaje crudo antes de discretizar en severidad (ver ScoringPolicy) — el peso del peor término, por defecto. */
    public function setScore(float $score): self { $this->score = $score; return $this; }
    public function getScore(): float { return $this->score; }

    public function getFullName(): string { return $this->fullName; }
    public function getLanguage(): string { return $this->language; }

    /** @param array<string,float> $languages */ public function setLanguagesChecked(array $languages): self { $this->languagesChecked = $languages; return $this; }
    /** @return array<string,float> */ public function getLanguagesChecked(): array { return $this->languagesChecked; }

    /** @return array<int,string> una frase por término marcado, para quien revise el caso */
    public function getExplanations(): array { return array_map([TermExplanation::class, 'of'], $this->getFlaggedTerms()); }

    /** @return array<string,mixed> */
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
            'explanations' => $this->getExplanations(),
        ];
    }
}
