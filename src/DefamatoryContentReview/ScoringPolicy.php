<?php

namespace DefamatoryContentReview;

/**
 * Cómo se convierte una lista de términos marcados en una severidad y, de
 * ahí, en una decisión. Antes esto vivía repartido en cuatro sitios de
 * `DefamatoryContentReviewer` (una constante, una propiedad, dos métodos
 * privados): pesos de severidad, qué tipos de riesgo cuentan como graves
 * por defecto, dónde cortan las bandas none/low/medium/high y qué decisión
 * corresponde a cada una, todo fijo en el código. Ajustar la sensibilidad
 * del filtro exigía tocar la clase del motor. Aquí es un objeto de
 * configuración independiente que el motor recibe (o no — `default()`
 * reproduce exactamente el comportamiento anterior).
 *
 * Analogía con un filtro de spam: no es un booleano ("es/no es ofensivo"),
 * pero tampoco es un puntaje aditivo tipo bayesiano donde muchas señales
 * débiles se suman hasta cruzar un umbral. Por defecto es el PEOR término
 * encontrado, no la suma de todos — diez coincidencias de baja severidad no
 * se combinan en una severidad alta. Quien quiera ese comportamiento
 * aditivo puede pedirlo con `withAggregation('sum')`; sigue siendo opt-in,
 * no el default, porque sumar sin criterio castiga más a un nombre con
 * muchas palabras cortas que a uno con una sola palabra grave.
 *
 * Es inmutable: cada `with*()` devuelve una copia nueva, la instancia
 * original no cambia.
 */
final class ScoringPolicy
{
    private const VALID_AGGREGATIONS = ['max', 'sum'];

    /** @var array<string,float> etiqueta de severidad => peso numérico */
    private array $severityWeights;

    /** @var array<string,float> riskType => multiplicador (1.0 si no aparece) */
    private array $riskTypeWeights;

    /**
     * Tipos de riesgo que por sí solos justifican severidad alta cuando el
     * término concreto no declara la suya. La severidad de la propia
     * palabra siempre manda; esto es sólo el respaldo.
     *
     * @var array<int,string>
     */
    private array $highSeverityRiskTypes;

    /**
     * Bandas ordenadas de mayor a menor umbral: [[umbral, etiqueta], ...].
     * Un puntaje agregado <= 0 siempre es 'none', sin pasar por aquí — las
     * bandas sólo deciden entre las etiquetas de "algo se encontró".
     *
     * @var array<int,array{0:float,1:string}>
     */
    private array $bands;

    /** @var array<string,string> etiqueta de severidad => decisión */
    private array $decisionRules;

    /**
     * Etiquetas de severidad en las que una colisión de nombre o una
     * detección puramente fonética degradan la decisión configurada a
     * 'review' en vez de aplicarla tal cual — el resguardo que evita
     * rechazar en automático un apellido real o una inferencia sin
     * coincidencia literal.
     *
     * @var array<int,string>
     */
    private array $phoneticCapLabels;

    /** 'max' (el peor término manda) o 'sum' (se suman todos los pesos). */
    private string $aggregation;

    private function __construct(
        array $severityWeights,
        array $riskTypeWeights,
        array $highSeverityRiskTypes,
        array $bands,
        array $decisionRules,
        array $phoneticCapLabels,
        string $aggregation
    ) {
        $this->severityWeights = $severityWeights;
        $this->riskTypeWeights = $riskTypeWeights;
        $this->highSeverityRiskTypes = $highSeverityRiskTypes;
        $this->decisionRules = $decisionRules;
        $this->phoneticCapLabels = $phoneticCapLabels;
        $this->aggregation = self::validateAggregation($aggregation);

        // Se ordenan de mayor a menor umbral una sola vez, para que
        // severityFromScore() pueda simplemente recorrerlas en orden.
        usort($bands, fn(array $a, array $b) => $b[0] <=> $a[0]);
        $this->bands = $bands;
    }

    /**
     * Reproduce exactamente el comportamiento anterior a esta clase: pesos
     * none=0/low=1/medium=2/high=3, cortes en 1.5 y 2.5, alta severidad sin
     * apellido/fusión → rechazo, el resto → revisión o marca, agregación
     * por el peor término.
     */
    public static function default(): self
    {
        return new self(
            severityWeights: ['none' => 0.0, 'low' => 1.0, 'medium' => 2.0, 'high' => 3.0],
            riskTypeWeights: [],
            highSeverityRiskTypes: ['ordinario', 'moral', 'discapacidad', 'genero', 'religioso', 'etnico'],
            bands: [[2.5, 'high'], [1.5, 'medium'], [0.0, 'low']],
            decisionRules: [
                'none' => 'accept',
                'low' => 'accept_with_flag',
                'medium' => 'review',
                'high' => 'reject',
            ],
            phoneticCapLabels: ['high'],
            aggregation: 'max'
        );
    }

    // -----------------------------------------------------------------
    // Ajustes (inmutables: cada with* devuelve una copia)
    // -----------------------------------------------------------------

    /** @param array<string,float> $weights */
    public function withSeverityWeights(array $weights): self
    {
        $clone = clone $this;
        $clone->severityWeights = $weights;
        return $clone;
    }

    public function withRiskTypeWeight(string $riskType, float $weight): self
    {
        return $this->withRiskTypeWeights([$riskType => $weight] + $this->riskTypeWeights);
    }

    /** @param array<string,float> $weights */
    public function withRiskTypeWeights(array $weights): self
    {
        $clone = clone $this;
        $clone->riskTypeWeights = $weights;
        return $clone;
    }

    /** @param array<int,string> $riskTypes */
    public function withHighSeverityRiskTypes(array $riskTypes): self
    {
        $clone = clone $this;
        $clone->highSeverityRiskTypes = $riskTypes;
        return $clone;
    }

    /** @param array<int,array{0:float,1:string}> $bands [umbral, etiqueta] */
    public function withBands(array $bands): self
    {
        $clone = clone $this;
        usort($bands, fn(array $a, array $b) => $b[0] <=> $a[0]);
        $clone->bands = $bands;
        return $clone;
    }

    /** @param array<string,string> $rules etiqueta de severidad => decisión */
    public function withDecisionRules(array $rules): self
    {
        $clone = clone $this;
        $clone->decisionRules = $rules;
        return $clone;
    }

    /** @param array<int,string> $labels */
    public function withPhoneticCapLabels(array $labels): self
    {
        $clone = clone $this;
        $clone->phoneticCapLabels = $labels;
        return $clone;
    }

    public function withAggregation(string $mode): self
    {
        $clone = clone $this;
        $clone->aggregation = self::validateAggregation($mode);
        return $clone;
    }

    private static function validateAggregation(string $mode): string
    {
        if (!in_array($mode, self::VALID_AGGREGATIONS, true)) {
            throw new \InvalidArgumentException(
                "Modo de agregación '{$mode}' inválido: use " . implode(' o ', self::VALID_AGGREGATIONS) . '.'
            );
        }

        return $mode;
    }

    // -----------------------------------------------------------------
    // Cálculo
    // -----------------------------------------------------------------

    /**
     * Puntaje de un término: peso de su severidad (o el respaldo por
     * riskType si el término no declara severidad reconocida) multiplicado
     * por el peso propio de su riskType (1.0 si no se configuró ninguno).
     */
    public function scoreOf(array $match): float
    {
        $severity = $match['severity'] ?? null;

        if ($severity === null || !isset($this->severityWeights[$severity])) {
            $severity = in_array($match['riskType'] ?? '', $this->highSeverityRiskTypes, true)
                ? 'high'
                : 'medium';
        }

        $base = $this->severityWeights[$severity] ?? 0.0;
        $riskType = $match['riskType'] ?? '';
        $multiplier = $this->riskTypeWeights[$riskType] ?? 1.0;

        return $base * $multiplier;
    }

    /**
     * Combina los puntajes ya escalados por confianza de todos los términos
     * marcados en uno solo. 'max' (por defecto): el peor término manda.
     * 'sum': se suman todos — útil si se prefiere un comportamiento más
     * parecido a un filtro de spam aditivo, a costa de que un nombre con
     * muchos términos de baja severidad pueda superar a uno con un único
     * término grave.
     *
     * @param array<int,float> $scores
     */
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

    /**
     * Un puntaje agregado de 0 o menos es siempre 'none' (nada se
     * encontró), sin pasar por las bandas. Por encima de 0, la primera
     * banda cuyo umbral no supere el puntaje decide la etiqueta.
     */
    public function severityFromScore(float $score): string
    {
        if ($score <= 0.0) {
            return 'none';
        }

        foreach ($this->bands as [$threshold, $label]) {
            if ($score >= $threshold) {
                return $label;
            }
        }

        // Puntaje positivo pero por debajo de toda banda configurada: la
        // banda más baja de las declaradas es la que corresponde.
        $lowest = end($this->bands);
        return $lowest[1] ?? 'low';
    }

    /**
     * Decisión final para una severidad ya calculada. Si la regla
     * configurada para esa etiqueta es 'reject' y la etiqueta está en
     * `phoneticCapLabels`, una colisión de nombre o una detección
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

    /** Peso configurado para una etiqueta de severidad (0.0 si no existe). */
    public function weightOf(string $severity): float
    {
        return $this->severityWeights[$severity] ?? 0.0;
    }

    /** Etiqueta de la banda de mayor umbral — la "más grave" configurada. */
    public function topSeverityLabel(): string
    {
        return $this->bands[0][1] ?? 'high';
    }

    // -----------------------------------------------------------------
    // Introspección
    // -----------------------------------------------------------------

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

    /** @return array<int,array{0:float,1:string}> */
    public function getBands(): array
    {
        return $this->bands;
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

    public function getAggregation(): string
    {
        return $this->aggregation;
    }
}
