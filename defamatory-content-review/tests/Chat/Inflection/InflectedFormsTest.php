<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Las formas regulares que TopicInflector genera para las listas de chat. */
class InflectedFormsTest extends TestCase
{
    public static function regularForms(): array
    {
        return [
            'verbo en -ar' => ['spa', 'follar', 'verb', ['follo', 'folla', 'follamos', 'follan', 'folle', 'follaste', 'follaron', 'follaba', 'follare', 'follaria', 'follara', 'follando', 'follado', 'follar']],
            'verbo con pronombre pegado' => ['spa', 'follar', 'verb', ['follame', 'follarlo', 'follandola', 'follenme', 'follarse']],
            'verbo -ar con g→gu' => ['spa', 'chingar', 'verb', ['chingue', 'chingues', 'chinguen']],
            'verbo -ar con c→qu' => ['spa', 'sacar', 'verb', ['saque', 'saquen']],
            'verbo -er con g→j' => ['spa', 'coger', 'verb', ['coge', 'cogen', 'cojo', 'coja', 'cogi', 'cogieron', 'cogiendo', 'cogerlo']],
            'verbo -ir con g→j' => ['spa', 'exigir', 'verb', ['exijo', 'exige', 'exigio', 'exigiendo', 'exigid']],
            'sustantivo en vocal' => ['spa', 'bomba', 'noun', ['bomba', 'bombas']],
            'sustantivo en consonante' => ['spa', 'misil', 'noun', ['misil', 'misiles']],
            'sustantivo en -n' => ['spa', 'invasion', 'noun', ['invasiones']],
            'sustantivo en -z' => ['spa', 'vez', 'noun', ['veces']],
            'adjetivo en -o' => ['spa', 'desnudo', 'adj', ['desnudo', 'desnuda', 'desnudos', 'desnudas']],
            'adjetivo en consonante' => ['spa', 'violador', 'adj', ['violadora', 'violadores', 'violadoras']],
            'adjetivo invariable' => ['spa', 'terrorista', 'adj', ['terrorista', 'terroristas']],
            'verbo inglés' => ['eng', 'fuck', 'verb', ['fucks', 'fucked', 'fucking']],
            'verbo inglés en -e' => ['eng', 'massacre', 'verb', ['massacres', 'massacred', 'massacring']],
            'verbo inglés en -y' => ['eng', 'bully', 'verb', ['bullies', 'bullied', 'bullying']],
            'sustantivo inglés' => ['eng', 'missile', 'noun', ['missile', 'missiles']],
            'sustantivo inglés en -y' => ['eng', 'pussy', 'noun', ['pussies']],
            'sustantivo inglés en -s' => ['eng', 'bus', 'noun', ['buses']],
            'frase inglesa' => ['eng', 'dick pic', 'noun', ['dick pic', 'dick pics']],
        ];
    }

    #[DataProvider('regularForms')]
    public function testGeneratesTheRegularForms(string $language, string $lemma, string $kind, array $expected): void
    {
        $forms = TopicInflector::forms($language, $lemma, $kind);

        foreach ($expected as $form) {
            $this->assertContains($form, $forms, "$lemma ($kind) no genera «{$form}»");
        }
        $this->assertSame($forms, array_values(array_unique($forms)), 'hay formas repetidas');
    }

    public function testAnotherLanguageOrAnUnknownVerbClassStaysLiteral(): void
    {
        $this->assertSame(['bomba'], TopicInflector::forms('deu', 'bomba', 'noun'));
        $this->assertSame(['xyz'], TopicInflector::forms('spa', 'xyz', 'verb'));
    }

    public function testOnlyTheDocumentedFormKindsMakeUpWords(): void
    {
        $this->assertSame(['rojo'], TopicInflector::forms('eng', 'rojo', 'adj'));
    }
}
