<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\ScoringPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Con agregación 'sum', un término presente en varios diccionarios de una
 * familia no debe sumar dos veces. CrossLanguageValidationTest cubre el
 * conteo de términos; esto cubre el puntaje, que es lo que cambia la decisión.
 */
class RelatedLanguageDedupTest extends TestCase
{
    public function testSumAggregationIsNotInflatedByTheSharedTerm(): void
    {
        $reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');
        $reviewer->setPolicy(ScoringPolicy::default()->withAggregation('sum'));
        $policy = $reviewer->getPolicy();

        // "cerdo" sólo está en español; antes "idiota" se sumaba por español y portugués (5.78 en vez de 4.0).
        $crossed = $reviewer->related()->validate('Cerdo Idiota');
        $expected = array_sum(array_map(
            fn(array $t) => $policy->scoreOf($t),
            $reviewer->validateName('Cerdo Idiota')->getFlaggedTerms()
        ));

        $this->assertEqualsWithDelta($expected, $crossed->getScore(), 1e-9);
    }
}
