<?php

namespace DefamatoryContentReview;

/**
 * Motor de validación: nombre completo dentro, ValidationResult con
 * severidad y decisión fuera. Delega en cuatro colaboradores, cada uno
 * documentado en su archivo: `NameEvaluator`, `RiskReportBuilder`,
 * `LanguageAccess` (vía `languages()`), `RelatedLanguageValidator` (vía
 * `related()`).
 */
class DefamatoryContentReviewer
{
    private LanguageRegistry $registry;
    private string $language;
    private ScoringPolicy $policy;
    private LanguageAccess $languages;
    private NameEvaluator $evaluator;
    private RiskReportBuilder $reports;

    public function __construct(
        LanguageRegistry $registry,
        string $languageDir,
        string $language = 'spa',
        ?ScoringPolicy $policy = null
    ) {
        $this->registry = $registry;
        $this->language = $registry->resolve($language);
        $this->policy = $policy ?? ScoringPolicy::default();
        $this->languages = new LanguageAccess($registry, rtrim($languageDir, '/'));
        $this->evaluator = new NameEvaluator($this->languages);
        $this->reports = new RiskReportBuilder();
    }

    public static function create(string $configDir, string $language = 'spa', ?ScoringPolicy $policy = null): self
    {
        $configDir = rtrim($configDir, '/');

        return new self(LanguageRegistry::fromConfigDirectory($configDir), $configDir . '/languages', $language, $policy);
    }

    /** Pesos, bandas, reglas de decisión y agregación. Ver ScoringPolicy. */
    public function getPolicy(): ScoringPolicy { return $this->policy; }
    public function setPolicy(ScoringPolicy $policy): self { $this->policy = $policy; return $this; }

    public function getLanguage(): string { return $this->language; }
    /** Acepta códigos de dos o tres letras; siempre se guarda el de tres. */
    public function setLanguage(string $language): self { $this->language = $this->registry->resolve($language); return $this; }

    /** Diccionarios y cobertura — ver LanguageAccess. */
    public function languages(): LanguageAccess { return $this->languages; }
    /** Validación contra idiomas emparentados — ver RelatedLanguageValidator. */
    public function related(): RelatedLanguageValidator { return new RelatedLanguageValidator($this->registry, $this->language, $this->evaluator, $this->policy); }

    public function validateName(string $name): ValidationResult
    {
        return $this->evaluator->evaluate($name, [$this->language => 1.0], $this->policy, $this->language);
    }

    public function validateFullName(string $firstName, string $lastName): ValidationResult
    {
        $result = $this->validateName(trim($firstName . ' ' . $lastName));
        $this->evaluator->applyPhoneticChecks($result, $firstName, $lastName, $this->language, $this->policy);

        return $result;
    }

    /** @param array<int,string> $names @return array<int,ValidationResult> */
    public function batchValidateNames(array $names): array { return array_map(fn(string $n) => $this->validateName($n), $names); }

    /** @param array<int,string> $fullNames @return array<int,ValidationResult> */
    public function batchValidateFullNames(array $fullNames): array { return array_map(fn(string $n) => $this->validateName(trim($n)), $fullNames); }

    /** Idiomas explícitos, cada uno con confianza 1.0 — sin depender del modelo de parentesco. @param array<int,string> $languages */
    public function validateInLanguages(string $name, array $languages): ValidationResult
    {
        $set = [];
        foreach ($languages as $code) {
            $set[$this->registry->resolve($code)] = 1.0;
        }

        return $this->evaluator->evaluate($name, $set, $this->policy, $this->language);
    }

    /** Colisión de apellido o fusión fonética nunca rechazan solas: bajan a revisión. Ver ScoringPolicy::decisionFor(). */
    public function decide(ValidationResult $result): string
    {
        return $this->policy->decisionFor($result->getSeverity(), $result->hasNameCollision(), $result->hasOnlyPhoneticDetections());
    }

    public function getDetailedReport(ValidationResult $result): array
    {
        return $this->reports->build($result, $this->decide($result), $this->policy);
    }
}
