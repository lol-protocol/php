<?php

namespace DefamatoryContentReview;

/**
 * Cuánto pesa cada severidad, cuánto pesa cada tipo de riesgo, y a qué
 * severidad cae un término que no declara la suya. Colaborador interno de
 * ScoringPolicy — inmutable, igual que ella.
 */
final class ScoringWeights
{
    /** @param array<string,float> $severityWeights
     *  @param array<string,float> $riskTypeWeights
     *  @param array<int,string> $highSeverityRiskTypes */
    public function __construct(
        private readonly array $severityWeights,
        private readonly array $riskTypeWeights,
        private readonly array $highSeverityRiskTypes
    ) {
    }

    /** @param array<string,float> $weights */
    public function withSeverityWeights(array $weights): self
    {
        return new self($weights, $this->riskTypeWeights, $this->highSeverityRiskTypes);
    }

    public function withRiskTypeWeight(string $riskType, float $weight): self
    {
        return $this->withRiskTypeWeights([$riskType => $weight] + $this->riskTypeWeights);
    }

    /** @param array<string,float> $weights */
    public function withRiskTypeWeights(array $weights): self
    {
        return new self($this->severityWeights, $weights, $this->highSeverityRiskTypes);
    }

    /** @param array<int,string> $riskTypes */
    public function withHighSeverityRiskTypes(array $riskTypes): self
    {
        return new self($this->severityWeights, $this->riskTypeWeights, $riskTypes);
    }

    /**
     * Peso de un término: el de su severidad (o el respaldo por riskType si
     * no declara una reconocida) multiplicado por el peso propio de su
     * riskType (1.0 si no se configuró ninguno).
     */
    public function scoreOf(array $match): float
    {
        $severity = $match['severity'] ?? null;

        if ($severity === null || !isset($this->severityWeights[$severity])) {
            $severity = in_array($match['riskType'] ?? '', $this->highSeverityRiskTypes, true)
                ? 'high'
                : 'medium';
        }

        $base = $this->severityWeights[$severity] ?? $this->fallbackWeight();
        $multiplier = $this->riskTypeWeights[$match['riskType'] ?? ''] ?? 1.0;

        return $base * $multiplier;
    }

    /**
     * Si ni la severidad del término ni las etiquetas de respaldo existen en
     * los pesos configurados —pasa al renombrar las severidades con
     * `withSeverityWeights()`— un término ya marcado puntuaría 0 y el filtro
     * aceptaría todo en silencio. Ante una configuración incoherente, el
     * lado seguro de un filtro es sobremarcar: se usa el peso mayor.
     */
    private function fallbackWeight(): float
    {
        return $this->severityWeights === [] ? 0.0 : max($this->severityWeights);
    }

    public function weightOf(string $severity): float
    {
        return $this->severityWeights[$severity] ?? 0.0;
    }

    /** @return array<string,float> */
    public function getSeverityWeights(): array
    {
        return $this->severityWeights;
    }

    /** @return array<string,float> */
    public function getRiskTypeWeights(): array
    {
        return $this->riskTypeWeights;
    }

    /** @return array<int,string> */
    public function getHighSeverityRiskTypes(): array
    {
        return $this->highSeverityRiskTypes;
    }
}
