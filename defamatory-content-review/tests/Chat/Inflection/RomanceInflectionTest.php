<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Portugués, italiano y francés: las formas que generan sus TopicInflection y su efecto en el chat. */
class RomanceInflectionTest extends TestCase
{
    /** @return array<string,array{string,string,string,array<int,string>}> */
    public static function regularForms(): array
    {
        return [
            'verbo portugués -ar' => ['por', 'degolar', 'verb', ['degolo', 'degolou', 'degolaram', 'degolando', 'degolado', 'degolarei']],
            'portugués con pronombre (matá-lo, fode-me)' => ['por', 'degolar', 'verb', ['degolalo', 'degolarte', 'degolame']],
            'verbo portugués -car → qu' => ['por', 'ficar', 'verb', ['fique', 'fiquem', 'fico']],
            'verbo portugués -ger → j' => ['por', 'proteger', 'verb', ['protejo', 'proteja', 'protege']],
            'sustantivo portugués en -ão' => ['por', 'invasao', 'noun', ['invasoes']],
            'sustantivo portugués en -il' => ['por', 'missil', 'noun', ['misseis']],
            'sustantivo portugués en -m' => ['por', 'homem', 'noun', ['homens']],
            'adjetivo portugués' => ['por', 'atentado', 'adj', ['atentados', 'atentada', 'atentadas']],
            'verbo italiano -are' => ['ita', 'massacrare', 'verb', ['massacro', 'massacrano', 'massacrato', 'massacrerebbe', 'massacrando']],
            'italiano con pronombre' => ['ita', 'sgozzare', 'verb', ['sgozzarti', 'sgozzandolo', 'sgozzala']],
            'verbo italiano -care → ch' => ['ita', 'cercare', 'verb', ['cerchi', 'cerchero', 'cerco']],
            'verbo italiano -giare' => ['ita', 'mangiare', 'verb', ['mangio', 'mangi', 'mangero']],
            'verbo italiano -ere' => ['ita', 'uccidere', 'verb', ['uccido', 'ucciderti', 'uccidendolo']],
            'sustantivo italiano' => ['ita', 'pompino', 'noun', ['pompini']],
            'sustantivo italiano -ista' => ['ita', 'terrorista', 'noun', ['terroristi', 'terroriste']],
            'sustantivo italiano -co' => ['ita', 'porco', 'noun', ['porci', 'porchi']],
            'adjetivo italiano' => ['ita', 'nudo', 'adj', ['nuda', 'nudi', 'nude']],
            'verbo francés -er' => ['fra', 'massacrer', 'verb', ['massacre', 'massacrons', 'massacrent', 'massacrerai', 'massacree']],
            'francés imperativo con pronombre' => ['fra', 'baiser', 'verb', ['baisemoi', 'baisele']],
            'verbo francés -ger' => ['fra', 'egorger', 'verb', ['egorgeons', 'egorgeait']],
            'verbo francés -ir' => ['fra', 'punir', 'verb', ['punis', 'punissons', 'punissent']],
            'sustantivo francés' => ['fra', 'viol', 'noun', ['viols']],
            'sustantivo francés -al' => ['fra', 'cheval', 'noun', ['chevaux']],
            'adjetivo francés' => ['fra', 'nu', 'adj', ['nue', 'nus', 'nues']],
            'adjetivo francés -eux' => ['fra', 'dangereux', 'adj', ['dangereuse', 'dangereuses']],
        ];
    }

    /** @param array<int,string> $expected */
    #[DataProvider('regularForms')]
    public function testGeneratesTheRegularForms(string $language, string $lemma, string $kind, array $expected): void
    {
        $forms = TopicInflector::forms($language, $lemma, $kind);

        foreach ($expected as $form) {
            $this->assertContains($form, $forms, "$lemma ($kind) no genera «{$form}»");
        }
        $this->assertSame($forms, array_values(array_unique($forms)), 'hay formas repetidas');
    }

    /** @return array<string,array{string,string}> */
    public static function inflectedLines(): array
    {
        return [
            'portugués' => ['por', 'eles massacraram a aldeia'],
            'portugués con pronombre' => ['por', 'vou degolá-lo amanhã'],
            'italiano' => ['ita', 'hanno sterminato tutti'],
            'francés' => ['fra', 'ils ont massacré le village'],
        ];
    }

    #[DataProvider('inflectedLines')]
    public function testTheChatSeesTheInflectedForm(string $language, string $line): void
    {
        $this->assertSame('reject', ChatLineReviewer::create(__DIR__ . '/../../../config', $language)->review($line)->getDecision(), $line);
    }
}
