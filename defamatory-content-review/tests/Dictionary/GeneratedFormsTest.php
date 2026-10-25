<?php

namespace Tests\Dictionary;

use DefamatoryContentReview\Dictionary\WordList;
use PHPUnit\Framework\TestCase;

/** Una forma generada nunca reemplaza a una entrada escrita, aunque con los acentos plegados sean la misma clave. */
class GeneratedFormsTest extends TestCase
{
    public function testAGeneratedFormDoesNotOverrideAWrittenEntryWithTheSameFoldedKey(): void
    {
        // «curvă» (lema, high) genera «curva» (con el artículo); con ă → a es la misma clave.
        $list = new WordList(['words' => ['gen' => [['word' => 'curvă', 'riskType' => 'genero', 'severity' => 'high', 'forms' => 'noun']]]], 'ron');

        $this->assertSame('curvă', $list->search('curva')['original']);
        $this->assertSame('high', $list->search('curva')['severity']);
        $this->assertSame('curve', $list->search('curve')['original'], 'las formas distintas sí se generan');
    }

    public function testAWrittenEntryOverridesAnEarlierGeneratedForm(): void
    {
        $list = new WordList(['words' => ['gen' => [
            ['word' => 'curvă', 'riskType' => 'genero', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'curve', 'riskType' => 'genero', 'severity' => 'low'],
        ]]], 'ron');

        $this->assertSame('low', $list->search('curve')['severity']);
    }
}
