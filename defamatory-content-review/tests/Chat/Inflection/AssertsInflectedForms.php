<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;

/** La comprobación que repetían las pruebas de flexión: las formas esperadas están, el lema va primero y no hay vacías ni repetidas. */
trait AssertsInflectedForms
{
    /**
     * @param list<string> $expected
     * @param bool $tagged las formas salen de `tagged()` (con sus rasgos) en vez de `forms()`
     */
    private function assertFormsInclude(string $language, string $lemma, string $kind, array $expected, bool $tagged = false): void
    {
        $forms = $tagged ? array_keys(TopicInflector::tagged($language, $lemma, $kind)) : TopicInflector::forms($language, $lemma, $kind);

        $this->assertSame([], array_diff($expected, $forms), "{$language} {$lemma}");
        $this->assertSame($lemma, $forms[0], 'el lema va primero');
        $this->assertNotContains('', $forms);
        $this->assertSame($forms, array_values(array_unique($forms)));
    }
}
