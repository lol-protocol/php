<?php

namespace DefamatoryContentReview;

/**
 * Qué decisión corresponde a cada etiqueta de severidad, y en cuáles de
 * ellas una colisión de nombre o una detección puramente fonética degradan
 * un `reject` a `review`. Colaborador interno de ScoringPolicy — inmutable,
 * igual que ella.
 */
final class DecisionTable
{
    /** @param array<string,string> $decisionRules etiqueta de severidad => decisión
     *  @param array<int,string> $phoneticCapLabels */
    public function __construct(
        private readonly array $decisionRules,
        private readonly array $phoneticCapLabels
    ) {
    }

    /** @param array<string,string> $rules */
    public function withDecisionRules(array $rules): self
    {
        return new self($rules, $this->phoneticCapLabels);
    }

    /** @param array<int,string> $labels */
    public function withPhoneticCapLabels(array $labels): self
    {
        return new self($this->decisionRules, $labels);
    }

    /**
     * Si la regla configurada para esta severidad es 'reject' y la etiqueta
     * está en `phoneticCapLabels`, una colisión de nombre o una detección
     * puramente fonética la degradan a 'review' — nunca se rechaza en
     * automático sólo por inferencia o sobre un apellido documentado.
     */
    public function decisionFor(string $severity, bool $hasNameCollision, bool $hasOnlyPhoneticDetections): string
    {
        $decision = $this->decisionRules[$severity] ?? 'accept_with_flag';

        if (
            $decision === 'reject'
            && in_array($severity, $this->phoneticCapLabels, true)
            && ($hasNameCollision || $hasOnlyPhoneticDetections)
        ) {
            return 'review';
        }

        return $decision;
    }

    /** @return array<string,string> */
    public function getDecisionRules(): array
    {
        return $this->decisionRules;
    }

    /** @return array<int,string> */
    public function getPhoneticCapLabels(): array
    {
        return $this->phoneticCapLabels;
    }
}
