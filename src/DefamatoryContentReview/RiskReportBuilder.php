<?php

namespace DefamatoryContentReview;

/**
 * Arma el reporte legible de un ValidationResult ya decidido: recomendación
 * en texto y el desglose por tipo de riesgo. Colaborador interno de
 * `DefamatoryContentReviewer::getDetailedReport()` — sin estado propio más
 * allá de recibir la policy en cada llamada.
 */
final class RiskReportBuilder
{
    private const DESCRIPTIONS = [
        'animal' => 'Comparación con animales',
        'intelectual' => 'Menoscabo de la capacidad intelectual',
        'discapacidad' => 'Referencia despectiva a discapacidad',
        'fisico' => 'Menoscabo de la apariencia física',
        'moral' => 'Imputación moral o delictiva',
        'genero' => 'Insulto por género u orientación sexual',
        'ordinario' => 'Léxico soez u obsceno',
        'burlesco' => 'Burla o ridiculización',
        'etnico' => 'Insulto étnico o racial',
        'religioso' => 'Insulto religioso',
        'fonetico' => 'Fusión fonética entre nombre y apellido',
    ];

    public function build(ValidationResult $result, string $decision, ScoringPolicy $policy): array
    {
        return $result->toArray() + [
            'decision' => $decision,
            'recommendation' => $this->recommendationFor($decision, $result),
            'riskAnalysis' => $this->analyzeRisks($result, $policy),
        ];
    }

    /**
     * Un término que además es apellido documentado nunca se rechaza solo:
     * baja a revisión humana. Lo mismo para una fusión fonética — es
     * inferencia, no coincidencia literal.
     */
    private function recommendationFor(string $decision, ValidationResult $result): string
    {
        $types = implode(', ', $result->getFlaggedRiskTypes());

        return match ($decision) {
            'reject' => "Rechazar: contenido gravemente ofensivo (tipos de riesgo: {$types}).",
            'review' => match (true) {
                $result->hasNameCollision() => "Revisión humana: coincide con términos ofensivos ({$types}) " .
                    "pero también con apellidos documentados.",
                $result->hasOnlyPhoneticDetections() => "Revisión humana: nombre y apellido, fusionados, " .
                    "componen un término ofensivo ({$types}) que ninguno de los dos tiene por separado.",
                default => "Revisión humana: términos potencialmente ofensivos ({$types}).",
            },
            'accept_with_flag' => "Aceptar con marca: términos de bajo riesgo ({$types}).",
            default => 'Aceptar: sin contenido difamatorio detectado.',
        };
    }

    private function analyzeRisks(ValidationResult $result, ScoringPolicy $policy): array
    {
        $analysis = [];

        foreach ($result->getFlaggedRiskTypes() as $riskType) {
            $terms = $result->getTermsByRiskType($riskType);
            $worst = 'low';

            foreach ($terms as $term) {
                if ($policy->weightOf($term['severity']) > $policy->weightOf($worst)) {
                    $worst = $term['severity'];
                }
            }

            $analysis[$riskType] = [
                'description' => self::DESCRIPTIONS[$riskType] ?? $riskType,
                'level' => $worst,
                'isSevere' => $worst === $policy->topSeverityLabel(),
                'termCount' => count($terms),
                'languages' => array_values(array_unique(array_column($terms, 'sourceLanguage'))),
                'detectionMethods' => array_values(array_unique(array_column($terms, 'detectionMethod'))),
            ];
        }

        return $analysis;
    }
}
