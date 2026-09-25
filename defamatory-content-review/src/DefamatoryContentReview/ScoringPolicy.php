<?php

namespace DefamatoryContentReview;

/**
 * Cómo se convierte una lista de términos marcados en severidad y decisión.
 * Compone tres colaboradores inmutables — `ScoringWeights`, `SeverityBands`
 * y `DecisionTable`, cada uno en su propio archivo — sin cambiar la API:
 * sigue siendo un único objeto con los mismos `with*()`.
 *
 * No es un booleano ni un puntaje aditivo tipo bayesiano: por defecto es el
 * PEOR término encontrado, no la suma (`withAggregation('sum')` para lo
 * contrario, opt-in). Inmutable: cada `with*()` devuelve una copia nueva.
 */
final class ScoringPolicy
{
    private const VALID_AGGREGATIONS = ['max', 'sum'];

    private function __construct(
        private readonly ScoringWeights $weights,
        private readonly SeverityBands $bands,
        private readonly DecisionTable $decisions,
        private readonly string $aggregation
    ) {
    }

    /** Pesos none=0/low=1/medium=2/high=3, cortes 1.5/2.5, rechazo en alta
     *  salvo apellido/fusión, agregación por el peor término: de siempre. */
    public static function default(): self
    {
        return new self(
            new ScoringWeights(
                severityWeights: ['none' => 0.0, 'low' => 1.0, 'medium' => 2.0, 'high' => 3.0],
                riskTypeWeights: [],
                highSeverityRiskTypes: ['ordinario', 'moral', 'discapacidad', 'genero', 'religioso', 'etnico'],
            ),
            new SeverityBands([[2.5, 'high'], [1.5, 'medium'], [0.0, 'low']]),
            new DecisionTable(
                decisionRules: ['none' => 'accept', 'low' => 'accept_with_flag', 'medium' => 'review', 'high' => 'reject'],
                phoneticCapLabels: ['high'],
            ),
            'max'
        );
    }

    // -- Ajustes: cada with* devuelve una copia --------------------------
    /** @param array<string,float> $weights */ public function withSeverityWeights(array $weights): self { return $this->withWeights($this->weights->withSeverityWeights($weights)); }
    public function withRiskTypeWeight(string $riskType, float $weight): self { return $this->withWeights($this->weights->withRiskTypeWeight($riskType, $weight)); }
    /** @param array<string,float> $weights */ public function withRiskTypeWeights(array $weights): self { return $this->withWeights($this->weights->withRiskTypeWeights($weights)); }
    /** @param array<int,string> $riskTypes */ public function withHighSeverityRiskTypes(array $riskTypes): self { return $this->withWeights($this->weights->withHighSeverityRiskTypes($riskTypes)); }
    /** @param array<int,array{0:float,1:string}> $bands */ public function withBands(array $bands): self { return new self($this->weights, $this->bands->withBands($bands), $this->decisions, $this->aggregation); }
    /** @param array<string,string> $rules */ public function withDecisionRules(array $rules): self { return $this->withDecisions($this->decisions->withDecisionRules($rules)); }
    /** @param array<int,string> $labels */ public function withPhoneticCapLabels(array $labels): self { return $this->withDecisions($this->decisions->withPhoneticCapLabels($labels)); }
    public function withAggregation(string $mode): self
    {
        if (!in_array($mode, self::VALID_AGGREGATIONS, true)) {
            throw new \InvalidArgumentException(
                "Modo de agregación '{$mode}' inválido: use " . implode(' o ', self::VALID_AGGREGATIONS) . '.'
            );
        }

        return new self($this->weights, $this->bands, $this->decisions, $mode);
    }

    private function withWeights(ScoringWeights $weights): self { return new self($weights, $this->bands, $this->decisions, $this->aggregation); }
    private function withDecisions(DecisionTable $decisions): self { return new self($this->weights, $this->bands, $decisions, $this->aggregation); }

    // -- Cálculo: delega en los tres colaboradores -----------------------
    /** @param array<string,mixed> $match */ public function scoreOf(array $match): float { return $this->weights->scoreOf($match); }
    /** Un puntaje <= 0 es siempre 'none', sin pasar por las bandas. */
    public function severityFromScore(float $score): string { return $score <= 0.0 ? 'none' : $this->bands->severityFromScore($score); }
    public function weightOf(string $severity): float { return $this->weights->weightOf($severity); }
    public function topSeverityLabel(): string { return $this->bands->topLabel(); }

    public function decisionFor(string $severity, bool $hasNameCollision, bool $hasOnlyPhoneticDetections): string { return $this->decisions->decisionFor($severity, $hasNameCollision, $hasOnlyPhoneticDetections); }

    /** @param array<int,float> $scores 'max' (por defecto): el peor término manda. 'sum': se acumulan todos. */
    public function aggregate(array $scores): float
    {
        if ($scores === []) {
            return 0.0;
        }

        return match ($this->aggregation) {
            'sum' => array_sum($scores),
            default => max($scores),
        };
    }

    // -- Introspección -----------------------------------------------------
    /** @return array<string,float> */ public function getSeverityWeights(): array { return $this->weights->getSeverityWeights(); }
    /** @return array<string,float> */ public function getRiskTypeWeights(): array { return $this->weights->getRiskTypeWeights(); }
    /** @return array<int,string> */ public function getHighSeverityRiskTypes(): array { return $this->weights->getHighSeverityRiskTypes(); }
    /** @return array<int,array{0:float,1:string}> */ public function getBands(): array { return $this->bands->all(); }
    /** @return array<string,string> */ public function getDecisionRules(): array { return $this->decisions->getDecisionRules(); }
    /** @return array<int,string> */ public function getPhoneticCapLabels(): array { return $this->decisions->getPhoneticCapLabels(); }
    public function getAggregation(): string { return $this->aggregation; }
}
