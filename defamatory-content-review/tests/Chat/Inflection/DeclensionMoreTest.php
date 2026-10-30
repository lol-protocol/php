<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Finés, árabe, hindi y tagalo (FinnishInflection, ArabicInflection, HindiInflection, TagalogInflection). */
class DeclensionMoreTest extends TestCase
{
    /** @return array<string,array{string,string,string,list<string>}> idioma, lema, tipo, formas que deben estar */
    public static function forms(): array
    {
        return [
            'finés -u, raíz fuerte y débil' => ['fin', 'vittu', 'noun', ['vittua', 'vittuun', 'vitun', 'vitut', 'vittuja']],
            'finés -a' => ['fin', 'paska', 'noun', ['paskaa', 'paskan', 'paskat', 'paskoja', 'paskasta']],
            'finés -i' => ['fin', 'idiootti', 'noun', ['idioottia', 'idiootin', 'idiootit', 'idiootteja']],
            'finés vocales anteriores' => ['fin', 'lehmä', 'noun', ['lehmää', 'lehmän', 'lehmät', 'lehmiä', 'lehmästä']],
            'finés vocal larga' => ['fin', 'kusipää', 'noun', ['kusipäätä', 'kusipään', 'kusipäät', 'kusipäitä']],
            'finés -nen' => ['fin', 'loinen', 'noun', ['loisen', 'loiset', 'loista']],
            'finés -us' => ['fin', 'lurjus', 'noun', ['lurjuksen', 'lurjukset', 'lurjusta']],
            'árabe plural roto' => ['ara', 'كلب', 'noun', ['كلاب']],
            'árabe plural sano' => ['ara', 'محتال', 'noun', ['محتالون', 'محتالين']],
            'árabe femenino' => ['ara', 'عاهرة', 'noun', ['عاهرات']],
            'hindi -ा' => ['hin', 'कुत्ता', 'noun', ['कुत्ते', 'कुत्तों']],
            'hindi -िया' => ['hin', 'कुतिया', 'noun', ['कुतियाँ', 'कुतियों']],
            'hindi -ी' => ['hin', 'रंडी', 'noun', ['रंडियाँ', 'रंडियों']],
            'hindi -ू' => ['hin', 'बुद्धू', 'noun', ['बुद्धुओं']],
            'hindi consonante' => ['hin', 'बेवकूफ', 'noun', ['बेवकूफों']],
            'hindi adjetivo' => ['hin', 'गंदा', 'adj', ['गंदे', 'गंदी']],
            'tagalo ligamento' => ['tgl', 'tanga', 'noun', ['tangang']],
            'tagalo -n' => ['tgl', 'kapon', 'noun', ['kapong']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('forms')]
    public function testForms(string $language, string $lemma, string $kind, array $expected): void
    {
        $forms = array_keys(TopicInflector::tagged($language, $lemma, $kind));

        $this->assertSame([], array_diff($expected, $forms), "{$language} {$lemma}");
        $this->assertSame($lemma, $forms[0]);
        $this->assertSame($forms, array_values(array_unique($forms)));
    }

    /** @return array<string,array{string,string}> */
    public static function lines(): array
    {
        return [
            'finés partitivo' => ['fin', 'sinä olet vittua'],
            'finés plural' => ['fin', 'te idiootit'],
            'árabe plural roto' => ['ara', 'أنتم كلاب'],
            'árabe plural sano' => ['ara', 'أنتم محتالون'],
            'tagalo ligamento' => ['tgl', 'tangang bata'],
        ];
    }

    #[DataProvider('lines')]
    public function testGeneratedFormsAreFoundButNeverBlock(string $language, string $line): void
    {
        $result = ChatLineReviewer::create(__DIR__ . '/../../../config', $language)->review($line);

        $this->assertContains($result->getDecision(), ['review', 'reject'], $line);
    }
}
