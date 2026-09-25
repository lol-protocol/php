<?php

namespace Tests;

use DefamatoryContentReview\ScoringPolicy;
use PHPUnit\Framework\TestCase;

/** Reglas de decisión y tope contra rechazo automático — DecisionTable, vía ScoringPolicy. */
class DecisionTableTest extends TestCase
{
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
}
