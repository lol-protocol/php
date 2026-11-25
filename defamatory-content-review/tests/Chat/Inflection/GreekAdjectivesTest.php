<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Adjetivos griegos en -ος: género, número, caso y comparativo (GreekInflection), medidos contra UniMorph. */
class GreekAdjectivesTest extends TestCase
{
    use AssertsInflectedForms;

    /** @return array<string,array{string,list<string>}> lema, formas que deben estar */
    public static function adjectives(): array
    {
        return [
            // las terminaciones salen sin tilde: el diccionario pliega las tildes al buscar
            'género y número' => ['κακός', ['κακη', 'κακο', 'κακοι', 'κακες', 'κακα', 'κακου']],
            'comparativo declinado' => ['κακός', ['κακοτερος', 'κακοτερη', 'κακοτερο', 'κακοτεροι', 'κακοτερων']],
            'lema sin tilde' => ['χαζος', ['χαζη', 'χαζοτερος', 'χαζοτερη']],
            'la tilde del tema se conserva' => ['ηλίθιος', ['ηλίθια', 'ηλίθιοτερος', 'ηλίθιοτερο']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('adjectives')]
    public function testAdjectiveForms(string $lemma, array $expected): void
    {
        $this->assertFormsInclude('ell', $lemma, 'adj', $expected);
    }

    public function testNounsAreNotGivenComparatives(): void
    {
        $this->assertNotContains('κακοτερος', TopicInflector::forms('ell', 'κακός', 'noun'));
    }
}
