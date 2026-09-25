<?php

namespace Tests;

use DefamatoryContentReview\ScoringPolicy;
use PHPUnit\Framework\TestCase;

/** Pesos por severidad y por tipo de riesgo — ScoringWeights, vía ScoringPolicy. */
class ScoringWeightsTest extends TestCase
{
    public function testDefaultScoreOfMatchesOriginalSeverityValues(): void
    {
        $policy = ScoringPolicy::default();

        $this->assertSame(0.0, $policy->scoreOf(['severity' => 'none', 'riskType' => 'ordinario']));
        $this->assertSame(1.0, $policy->scoreOf(['severity' => 'low', 'riskType' => 'ordinario']));
        $this->assertSame(2.0, $policy->scoreOf(['severity' => 'medium', 'riskType' => 'ordinario']));
        $this->assertSame(3.0, $policy->scoreOf(['severity' => 'high', 'riskType' => 'ordinario']));
    }

    public function testScoreOfFallsBackToHighSeverityRiskTypesWhenSeverityMissing(): void
    {
        $policy = ScoringPolicy::default();

        // 'etnico' está en la lista de respaldo por defecto: sin severidad
        // declarada, cuenta como 'high' (peso 3.0).
        $this->assertSame(3.0, $policy->scoreOf(['riskType' => 'etnico']));
        // 'burlesco' no está: cae a 'medium' (peso 2.0).
        $this->assertSame(2.0, $policy->scoreOf(['riskType' => 'burlesco']));
    }

    public function testRiskTypeWeightMultipliesTheSeverityWeight(): void
    {
        $policy = ScoringPolicy::default()->withRiskTypeWeight('etnico', 1.5);

        // 'medium' (2.0) * 1.5 = 3.0, aunque la palabra sólo declare severidad media.
        $this->assertSame(3.0, $policy->scoreOf(['severity' => 'medium', 'riskType' => 'etnico']));
        // Otro riskType sin peso propio no se ve afectado.
        $this->assertSame(2.0, $policy->scoreOf(['severity' => 'medium', 'riskType' => 'animal']));
    }

    public function testCustomSeverityWeightsChangeTheScore(): void
    {
        $policy = ScoringPolicy::default()->withSeverityWeights([
            'none' => 0.0, 'low' => 0.5, 'medium' => 1.0, 'high' => 5.0,
        ]);

        $this->assertSame(5.0, $policy->scoreOf(['severity' => 'high', 'riskType' => 'ordinario']));
        $this->assertSame(0.5, $policy->scoreOf(['severity' => 'low', 'riskType' => 'ordinario']));
    }

    /**
     * Al renombrar las severidades, las del diccionario ('high'...) ya no
     * existen en el mapa — ni las etiquetas de respaldo. Un término marcado
     * no puede puntuar 0 por eso: el filtro aceptaría todo en silencio.
     */
    public function testRenamedSeverityLabelsDoNotSilentlyScoreZero(): void
    {
        $policy = ScoringPolicy::default()->withSeverityWeights([
            'none' => 0.0, 'leve' => 1.0, 'medio' => 2.0, 'critico' => 3.0,
        ]);

        $this->assertSame(3.0, $policy->scoreOf(['severity' => 'high', 'riskType' => 'moral']));
    }

    public function testWithersReturnNewInstancesWithoutMutatingTheOriginal(): void
    {
        $original = ScoringPolicy::default();
        $original->withSeverityWeights(['high' => 99.0]);

        // El objeto original no cambió: sigue devolviendo el peso de siempre.
        $this->assertSame(3.0, $original->scoreOf(['severity' => 'high', 'riskType' => 'ordinario']));
    }
}
