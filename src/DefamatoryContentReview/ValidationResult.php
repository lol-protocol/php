<?php

namespace DefamatoryContentReview;

class ValidationResult
{
    private string $fullName;
    private string $language;
    private bool $isValid;
    private string $severity = 'none';

    /** @var array<int,array> */
    private array $flaggedTerms = [];
    private array $flaggedCategories = [];
    private array $flaggedRiskTypes = [];
    /** @var array<string,float> idiomas consultados => afinidad con el principal */
    private array $languagesChecked = [];

    public function __construct(string $fullName, bool $isValid = true, string $language = 'spa')
    {
        $this->fullName = $fullName;
        $this->isValid = $isValid;
        $this->language = $language;
        $this->languagesChecked = [$language => 1.0];
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

    /**
     * @param array $term Datos del término tal como los devuelve WordList, más
     *                    `sourceLanguage` y `confidence` cuando la coincidencia
     *                    viene de un idioma asociado y no del principal, y
     *                    `detectionMethod` ('literal' por defecto;
     *                    'phonetic_fusion' o 'phonetic_variant' cuando viene
     *                    de PhoneticFusionDetector).
     */
    public function addFlaggedTerm(array $term): self
    {
        $entry = [
            'term' => $term['found'] ?? $term['original'] ?? '',
            'category' => $term['category'] ?? 'desconocida',
            'riskType' => $term['riskType'] ?? 'ordinario',
            'severity' => $term['severity'] ?? 'medium',
            'nameCollision' => $term['nameCollision'] ?? false,
            'sourceLanguage' => $term['sourceLanguage'] ?? $this->language,
            'confidence' => $term['confidence'] ?? 1.0,
            'detectionMethod' => $term['detectionMethod'] ?? 'literal',
        ];

        $this->flaggedTerms[] = $entry;

        if (!in_array($entry['category'], $this->flaggedCategories, true)) {
            $this->flaggedCategories[] = $entry['category'];
        }

        if (!in_array($entry['riskType'], $this->flaggedRiskTypes, true)) {
            $this->flaggedRiskTypes[] = $entry['riskType'];
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
        return array_values(array_filter(
            $this->flaggedTerms,
            fn(array $t) => $t['riskType'] === $riskType
        ));
    }

    public function getTermsByLanguage(string $language): array
    {
        return array_values(array_filter(
            $this->flaggedTerms,
            fn(array $t) => $t['sourceLanguage'] === $language
        ));
    }

    /** Coincidencias halladas en el idioma principal, no en los asociados. */
    public function getPrimaryLanguageTerms(): array
    {
        return $this->getTermsByLanguage($this->language);
    }

    /**
     * Un término marcado que además es apellido o nombre documentado. Estos
     * casos van a revisión humana en lugar de rechazarse en automático.
     */
    public function hasNameCollision(): bool
    {
        foreach ($this->flaggedTerms as $term) {
            if ($term['nameCollision']) {
                return true;
            }
        }

        return false;
    }

    public function getNameCollisionTerms(): array
    {
        return array_values(array_filter($this->flaggedTerms, fn(array $t) => $t['nameCollision']));
    }

    public function getTermsByDetectionMethod(string $method): array
    {
        return array_values(array_filter($this->flaggedTerms, fn(array $t) => $t['detectionMethod'] === $method));
    }

    public function getPhoneticFusionTerms(): array
    {
        return $this->getTermsByDetectionMethod('phonetic_fusion');
    }

    public function getPhoneticVariantTerms(): array
    {
        return $this->getTermsByDetectionMethod('phonetic_variant');
    }

    /**
     * Todo lo marcado proviene sólo de inferencia fonética (fusión o
     * variante), nada de coincidencia literal directa. Es la señal de menor
     * certeza: nunca debe bastar por sí sola para un rechazo automático.
     */
    public function hasOnlyPhoneticDetections(): bool
    {
        if (empty($this->flaggedTerms)) {
            return false;
        }

        foreach ($this->flaggedTerms as $term) {
            if ($term['detectionMethod'] === 'literal') {
                return false;
            }
        }

        return true;
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

    public function setLanguagesChecked(array $languages): self
    {
        $this->languagesChecked = $languages;
        return $this;
    }

    /** @return array<string,float> */
    public function getLanguagesChecked(): array
    {
        return $this->languagesChecked;
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
            'languagesChecked' => $this->languagesChecked,
            'isValid' => $this->isValid,
            'severity' => $this->severity,
            'flaggedTerms' => $this->flaggedTerms,
            'flaggedCategories' => $this->flaggedCategories,
            'flaggedRiskTypes' => $this->flaggedRiskTypes,
            'termsByRiskType' => $termsByRiskType,
            'hasNameCollision' => $this->hasNameCollision(),
            'hasOnlyPhoneticDetections' => $this->hasOnlyPhoneticDetections(),
            'totalFlagged' => count($this->flaggedTerms),
        ];
    }
}
