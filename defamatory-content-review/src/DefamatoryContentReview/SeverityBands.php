<?php

namespace DefamatoryContentReview;

/**
 * Dónde cortan las bandas de severidad por encima de 'none'. Colaborador
 * interno de ScoringPolicy — inmutable, igual que ella.
 *
 * Un puntaje <= 0 siempre es 'none' y ni pasa por aquí (lo decide
 * ScoringPolicy antes de consultar las bandas): éstas sólo reparten el
 * resto entre las etiquetas de "algo se encontró".
 */
final class SeverityBands
{
    /** @var array<int,array{0:float,1:string}> ordenadas de mayor a menor umbral */
    private array $bands;

    /** @param array<int,array{0:float,1:string}> $bands [umbral, etiqueta] */
    public function __construct(array $bands)
    {
        usort($bands, fn(array $a, array $b) => $b[0] <=> $a[0]);
        $this->bands = $bands;
    }

    /** @param array<int,array{0:float,1:string}> $bands */
    public function withBands(array $bands): self
    {
        return new self($bands);
    }

    /** La primera banda cuyo umbral no supere el puntaje decide la etiqueta. */
    public function severityFromScore(float $score): string
    {
        foreach ($this->bands as [$threshold, $label]) {
            if ($score >= $threshold) {
                return $label;
            }
        }

        // Puntaje positivo pero por debajo de toda banda configurada: la
        // banda más baja de las declaradas es la que corresponde.
        if (empty($this->bands)) {
            return 'low';
        }
        $lowest = end($this->bands);
        return $lowest[1];
    }

    /** Etiqueta de la banda de mayor umbral — la "más grave" configurada. */
    public function topLabel(): string
    {
        return $this->bands[0][1] ?? 'high';
    }

    /** @return array<int,array{0:float,1:string}> */
    public function all(): array
    {
        return $this->bands;
    }
}
