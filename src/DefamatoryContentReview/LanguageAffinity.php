<?php

namespace DefamatoryContentReview;

/**
 * Afinidad léxica entre idiomas ya resueltos a ISO 639-3. Colaborador
 * interno de `LanguageRegistry` — separado porque es un concepto propio
 * (parentesco, no identidad) con su propia estructura de datos.
 */
final class LanguageAffinity
{
    /** @var array<string,array<string,float>> */
    private array $scores = [];

    /** @param array<string,float> $pairs "a|b" => afinidad, declarado una sola vez por par */
    public function __construct(array $pairs, private readonly float $defaultThreshold)
    {
        foreach ($pairs as $pair => $score) {
            [$a, $b] = explode('|', $pair);
            $this->scores[$a][$b] = $score;
            $this->scores[$b][$a] = $score;
        }
    }

    /** 1.0 para un idioma consigo mismo; 0.0 si no hay par declarado. */
    public function between(string $a, string $b): float
    {
        return $a === $b ? 1.0 : ($this->scores[$a][$b] ?? 0.0);
    }

    /** @return array<string,float> código => afinidad, de mayor a menor */
    public function relatedTo(string $code, ?float $threshold): array
    {
        $related = array_filter(
            $this->scores[$code] ?? [],
            fn(float $score) => $score >= ($threshold ?? $this->defaultThreshold)
        );

        arsort($related);

        return $related;
    }
}
