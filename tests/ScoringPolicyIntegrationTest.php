<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\ScoringPolicy;
use PHPUnit\Framework\TestCase;

/** ScoringPolicy conectada al motor real — que ajustarla cambie el resultado de verdad. */
class ScoringPolicyIntegrationTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

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
