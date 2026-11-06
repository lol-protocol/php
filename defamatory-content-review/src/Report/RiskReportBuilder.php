<?php

namespace DefamatoryContentReview\Report;

use DefamatoryContentReview\Scoring\ScoringPolicy;

/**
 * Arma el reporte legible de un ValidationResult ya decidido: recomendación
 * en texto y el desglose por tipo de riesgo. Colaborador interno de
 * `DefamatoryContentReviewer::getDetailedReport()` — sin estado propio más
 * allá de recibir la policy en cada llamada.
 */
final class RiskReportBuilder
{
    private readonly string $messagesDir;

    /** @param string $riskCategoriesFile `config/risk-categories.php`: de su carpeta salen los mensajes (`messages/`) */
    public function __construct(string $riskCategoriesFile)
    {
        $this->messagesDir = dirname($riskCategoriesFile) . '/messages';
    }

    /** @return array<string,mixed> */
    public function build(ValidationResult $result, string $decision, ScoringPolicy $policy, string $locale = Messages::DEFAULT_LOCALE): array
    {
        return array_merge($result->toArray(), [
            'explanations' => $result->getExplanations($locale, $this->messagesDir),
            'decision' => $decision,
            'recommendation' => $this->recommendationFor($decision, $result, $locale),
            'riskAnalysis' => $this->analyzeRisks($result, $policy, $locale),
        ]);
    }

    /** Un término que además es apellido documentado nunca se rechaza solo: baja a revisión humana. Lo mismo para una fusión fonética — es inferencia, no coincidencia literal. */
    private function recommendationFor(string $decision, ValidationResult $result, string $locale): string
    {
        $key = match ($decision) {
            'reject' => 'reject',
            'review' => match (true) {
                $result->hasNameCollision() => 'review_collision',
                $result->hasOnlyPhoneticDetections() => 'review_phonetic',
                default => 'review',
            },
            'accept_with_flag' => 'accept_with_flag',
            default => 'accept',
        };

        return Messages::get('recommendation.' . $key, $locale, [implode(', ', $result->getFlaggedRiskTypes())], $this->messagesDir);
    }

    /** @return array<string,array<string,mixed>> */
    private function analyzeRisks(ValidationResult $result, ScoringPolicy $policy, string $locale): array
    {
        $analysis = [];

        foreach ($result->getFlaggedRiskTypes() as $riskType) {
            $terms = $result->getTermsByRiskType($riskType);
            $worst = $terms[0]['severity'] ?? 'none';

            foreach ($terms as $term) {
                if ($policy->weightOf($term['severity']) > $policy->weightOf($worst)) {
                    $worst = $term['severity'];
                }
            }

            $analysis[$riskType] = [
                'description' => Messages::find("category.$riskType.description", $locale, $this->messagesDir) ?? $riskType,
                'level' => $worst,
                'isSevere' => $this->isTopWeight($worst, $policy),
                'termCount' => count($terms),
                'languages' => array_values(array_unique(array_column($terms, 'sourceLanguage'))),
                'detectionMethods' => array_values(array_unique(array_column($terms, 'detectionMethod'))),
            ];
        }

        return $analysis;
    }

    /**
     * `$worst` es una severidad del diccionario ('high') y
     * `topSeverityLabel()` una etiqueta de banda: con las bandas por defecto
     * coinciden por casualidad, pero al renombrarlas comparar los nombres
     * daba siempre false, aun con la decisión en 'reject'. Se comparan los
     * pesos, que sí son el mismo lenguaje.
     */
    private function isTopWeight(string $severity, ScoringPolicy $policy): bool
    {
        $weights = $policy->getSeverityWeights();

        return $weights !== [] && $policy->weightOf($severity) >= max($weights);
    }
}
