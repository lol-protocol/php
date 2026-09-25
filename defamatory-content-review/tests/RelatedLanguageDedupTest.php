<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\ScoringPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Un término que está en varios diccionarios de una familia cuenta una sola
 * vez, aunque otro término del nombre sólo exista en uno de ellos y corra su
 * posición en la lista de ese idioma.
 */
class RelatedLanguageDedupTest extends TestCase
{
    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');
    }

    /** @return array<int,array> */
    private function idiotaTerms(string $name): array
    {
        $terms = $this->reviewer->related()->validate($name)->getFlaggedTerms();

        return array_values(array_filter($terms, fn(array $t) => $t['term'] === 'Idiota'));
    }

    public function testTermSharedWithARelatedLanguageIsCountedOnce(): void
    {
        // "cerdo" sólo está en español: corre "idiota" una posición en la
        // lista española respecto de la portuguesa, donde es el primer hallazgo.
        $idiota = $this->idiotaTerms('Cerdo Idiota');

        $this->assertCount(1, $idiota);
        $this->assertSame('spa', $idiota[0]['sourceLanguage'], 'Se conserva la de mayor confianza.');
    }

    public function testRepeatedTermInTheSameNameIsStillCountedTwice(): void
    {
        $this->assertCount(2, $this->idiotaTerms('Idiota Idiota'));
    }

    public function testSumAggregationIsNotInflatedByTheSharedTerm(): void
    {
        $this->reviewer->setPolicy(ScoringPolicy::default()->withAggregation('sum'));
        $policy = $this->reviewer->getPolicy();

        $crossed = $this->reviewer->related()->validate('Cerdo Idiota');
        $expected = array_sum(array_map(
            fn(array $t) => $policy->scoreOf($t),
            $this->reviewer->validateName('Cerdo Idiota')->getFlaggedTerms()
        ));

        $this->assertEqualsWithDelta($expected, $crossed->getScore(), 1e-9);
    }
}
