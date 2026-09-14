<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\ScoringPolicy;
use PHPUnit\Framework\TestCase;

/**
 * ScoringPolicy separa cómo se pesan los términos, dónde cortan las bandas
 * de severidad y qué decisión corresponde a cada una, de la mecánica del
 * motor (`DefamatoryContentReviewer`). `default()` tiene que reproducir el
 * comportamiento de siempre al milímetro — eso ya lo cubre el resto de la
 * batería de tests sin tocar una policy explícita. Aquí se prueba que
 * AJUSTAR la policy realmente cambia el resultado, y que el resguardo
 * contra rechazo automático (colisión de nombre / sólo fonético) sigue
 * aplicando aunque se reconfiguren bandas y reglas.
 */
class ScoringPolicyTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    // -----------------------------------------------------------------
    // Cálculo aislado (sin pasar por el motor)
    // -----------------------------------------------------------------

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

    public function testCustomBandsChangeSeverityBoundaries(): void
    {
        // Umbrales mucho más exigentes que el default (2.5/1.5): hace
        // falta más puntaje para llegar a 'high' o 'medium'.
        $policy = ScoringPolicy::default()->withBands([
            [5.0, 'high'],
            [3.0, 'medium'],
            [0.0, 'low'],
        ]);

        $this->assertSame('low', $policy->severityFromScore(2.0), 'Con las bandas por defecto esto sería medium.');
        $this->assertSame('medium', $policy->severityFromScore(4.0));
        $this->assertSame('high', $policy->severityFromScore(5.0));
        $this->assertSame('none', $policy->severityFromScore(0.0));
    }

    public function testBandsAreSortedRegardlessOfInputOrder(): void
    {
        $policy = ScoringPolicy::default()->withBands([
            [0.0, 'low'],
            [2.5, 'high'],
            [1.5, 'medium'],
        ]);

        $this->assertSame([[2.5, 'high'], [1.5, 'medium'], [0.0, 'low']], $policy->getBands());
    }

    public function testCustomDecisionRulesAreHonored(): void
    {
        // Política más permisiva: 'medium' también se acepta con marca en
        // vez de mandar a revisión.
        $policy = ScoringPolicy::default()->withDecisionRules([
            'none' => 'accept',
            'low' => 'accept_with_flag',
            'medium' => 'accept_with_flag',
            'high' => 'reject',
        ]);

        $this->assertSame('accept_with_flag', $policy->decisionFor('medium', false, false));
    }

    public function testUnknownSeverityLabelDefaultsToAcceptWithFlag(): void
    {
        $policy = ScoringPolicy::default();

        $this->assertSame('accept_with_flag', $policy->decisionFor('etiqueta-inventada', false, false));
    }

    public function testPhoneticCapLabelsAreConfigurable(): void
    {
        // Por defecto sólo 'high' se topea a review; se puede extender a
        // 'medium' también, o (como aquí) sacar 'high' de la lista para que
        // vuelva a rechazar aunque haya colisión de nombre.
        $capped = ScoringPolicy::default()->withPhoneticCapLabels(['high', 'medium']);
        $uncapped = ScoringPolicy::default()->withPhoneticCapLabels([]);

        $this->assertSame('review', $capped->decisionFor('high', true, false));
        $this->assertSame('reject', $uncapped->decisionFor('high', true, false));
    }

    public function testSumAggregationAddsInsteadOfTakingTheWorst(): void
    {
        $max = ScoringPolicy::default();
        $sum = ScoringPolicy::default()->withAggregation('sum');

        $scores = [1.0, 1.0, 1.0];

        $this->assertSame(1.0, $max->aggregate($scores), 'max: el peor término manda.');
        $this->assertSame(3.0, $sum->aggregate($scores), 'sum: todos se acumulan.');
    }

    public function testAggregateOfEmptyScoresIsZeroRegardlessOfMode(): void
    {
        $this->assertSame(0.0, ScoringPolicy::default()->aggregate([]));
        $this->assertSame(0.0, ScoringPolicy::default()->withAggregation('sum')->aggregate([]));
    }

    public function testInvalidAggregationModeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ScoringPolicy::default()->withAggregation('average');
    }

    public function testWithersReturnNewInstancesWithoutMutatingTheOriginal(): void
    {
        $original = ScoringPolicy::default();
        $original->withSeverityWeights(['high' => 99.0]);

        // El objeto original no cambió: sigue devolviendo el peso de siempre.
        $this->assertSame(3.0, $original->scoreOf(['severity' => 'high', 'riskType' => 'ordinario']));
    }

    // -----------------------------------------------------------------
    // Integración con el motor
    // -----------------------------------------------------------------

    public function testReviewerUsesDefaultPolicyWhenNoneIsGiven(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');

        $this->assertSame(ScoringPolicy::default()->getBands(), $reviewer->getPolicy()->getBands());
    }

    public function testCustomPolicyChangesTheDecisionForTheSameMatch(): void
    {
        // 'Cerda' es 'animal'/medium con nameCollision=true: por defecto
        // decisionFor('medium', ...) da 'review'. Con una policy más laxa
        // donde 'medium' se acepta con marca, el mismo nombre pasa distinto.
        $lenient = ScoringPolicy::default()->withDecisionRules([
            'none' => 'accept', 'low' => 'accept_with_flag',
            'medium' => 'accept_with_flag', 'high' => 'reject',
        ]);

        $strict = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
        $lenientReviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa', $lenient);

        $strictResult = $strict->validateName('Cerda');
        $lenientResult = $lenientReviewer->validateName('Cerda');

        $this->assertSame('review', $strict->decide($strictResult));
        $this->assertSame('accept_with_flag', $lenientReviewer->decide($lenientResult));
    }

    public function testSetPolicyChangesBehaviorOnAnExistingReviewer(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
        $strictPolicy = ScoringPolicy::default()->withBands([[0.5, 'high'], [0.0, 'low']]);

        $reviewer->setPolicy($strictPolicy);

        // Con el umbral de 'high' bajado a 0.5, hasta un término de
        // severidad baja (peso 1.0) ahora cae en 'high'.
        $result = $reviewer->validateName('zanahoria');
        $this->assertSame('high', $result->getSeverity());
    }

    public function testValidationResultExposesRawScore(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');

        $clean = $reviewer->validateName('María González');
        $this->assertSame(0.0, $clean->getScore());

        $flagged = $reviewer->validateName('Cerda');
        $this->assertGreaterThan(0.0, $flagged->getScore());
    }
}
