<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Declinación regular de los idiomas con casos (ver SuffixInflection y NordicInflection): formas que deben salir, y que lo generado nunca sobrepasa «revisión». */
class DeclensionTest extends TestCase
{
    /** @return array<string,array{string,string,string,list<string>}> idioma, lema, tipo, formas que deben estar */
    public static function forms(): array
    {
        return [
            'sueco -or' => ['swe', 'hora', 'noun', ['horor']],
            'sueco adjetivo' => ['swe', 'dum', 'adj', ['dumma', 'dumt']],
            'sueco verbo' => ['swe', 'knulla', 'verb', ['knullar', 'knullade', 'knullat']],
            'danés' => ['dan', 'idiot', 'noun', ['idioter']],
            'noruego' => ['nor', 'drittsekk', 'noun', ['drittsekker']],
            'ruso masculino' => ['rus', 'идиот', 'noun', ['идиота', 'идиоту', 'идиотом', 'идиоты', 'идиотов']],
            'ruso -к' => ['rus', 'дурак', 'noun', ['дураки', 'дураков']],
            'ruso -ец' => ['rus', 'подлец', 'noun', ['подлеца', 'подлецы', 'подлецов']],
            'ruso -ёл' => ['rus', 'козёл', 'noun', ['козла', 'козлы', 'козлов']],
            'polaco -ek' => ['pol', 'dupek', 'noun', ['dupka', 'dupki', 'dupków']],
            'ruso femenino' => ['rus', 'сука', 'noun', ['суки', 'суку', 'сукой']],
            'ruso adjetivo' => ['rus', 'тупой', 'adj', ['тупого', 'тупая', 'тупые']],
            'ruso verbo -овать' => ['rus', 'насиловать', 'verb', ['насилую', 'насиловал']],
            'ucraniano' => ['ukr', 'дурень', 'noun', ['дурня', 'дурню']],
            'ucraniano femenino' => ['ukr', 'сука', 'noun', ['суки', 'суці', 'суку']],
            'polaco masculino' => ['pol', 'idiota', 'noun', ['idioty', 'idiotę', 'idiotą']],
            'polaco femenino' => ['pol', 'suka', 'noun', ['suki', 'suce', 'sukę']],
            'polaco adjetivo' => ['pol', 'głupi', 'adj', ['głupiego', 'głupia', 'głupie']],
            'checo' => ['ces', 'idiot', 'noun', ['idiota', 'idioti', 'idioty', 'idiotů']],
            'checo femenino' => ['ces', 'kurva', 'noun', ['kurvy', 'kurvu', 'kurvou']],
            'eslovaco' => ['slk', 'idiot', 'noun', ['idiota', 'idioti', 'idiotov']],
            'búlgaro' => ['bul', 'идиот', 'noun', ['идиоти', 'идиотът', 'идиотите']],
            'búlgaro adjetivo' => ['bul', 'тъп', 'adj', ['тъпа', 'тъпо', 'тъпи']],
            'rumano palatalizado' => ['ron', 'idiot', 'noun', ['idioți', 'idiotul']],
            'rumano -st' => ['ron', 'prost', 'adj', ['proști', 'prostă']],
            'rumano femenino' => ['ron', 'curvă', 'noun', ['curve', 'curva']],
            'húngaro posterior' => ['hun', 'barom', 'noun', ['baromok', 'baromot', 'baromnak']],
            'húngaro anterior' => ['hun', 'seggfej', 'noun', ['seggfejek', 'seggfejet']],
            'griego -ας' => ['ell', 'μαλάκας', 'noun', ['μαλάκα', 'μαλάκες', 'μαλάκων']],
            'griego acentuado' => ['ell', 'μουνί', 'noun', ['μουνιου', 'μουνια']],
            'griego adjetivo' => ['ell', 'ηλίθιος', 'adj', ['ηλίθια', 'ηλίθιο', 'ηλίθιοι']],
            'hebreo' => ['heb', 'אידיוט', 'noun', ['אידיוטים']],
            'hebreo letra final' => ['heb', 'מטומטם', 'adj', ['מטומטמים', 'מטומטמה']],
            'hebreo femenino' => ['heb', 'זונה', 'noun', ['זונות']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('forms')]
    public function testForms(string $language, string $lemma, string $kind, array $expected): void
    {
        $forms = array_keys(TopicInflector::tagged($language, $lemma, $kind));

        $this->assertSame([], array_diff($expected, $forms), "{$language} {$lemma}");
        $this->assertSame($lemma, $forms[0], 'el lema va primero');
        $this->assertNotContains('', $forms);
        $this->assertSame($forms, array_values(array_unique($forms)));
    }

    public function testEveryFormOfADecliningLanguageIsInflected(): void
    {
        $tags = TopicInflector::tagged('rus', 'идиот', 'noun');

        $this->assertSame('sg', $tags['идиот']);
        $this->assertSame(['infl'], array_values(array_unique(array_diff_key($tags, ['идиот' => 1]))));
    }

    /** @return array<string,array{string,string}> */
    public static function lines(): array
    {
        return [
            'ruso plural' => ['rus', 'вы идиоты'],
            'polaco acusativo' => ['pol', 'ty idiotę'],
            'checo plural' => ['ces', 'jste idioti'],
            'búlgaro con artículo' => ['bul', 'ти си идиотът'],
            'rumano' => ['ron', 'sunteți idioți'],
            'húngaro' => ['hun', 'ti idióták'],
            'griego' => ['ell', 'είστε μαλάκες'],
            'hebreo' => ['heb', 'אתם אידיוטים'],
        ];
    }

    #[DataProvider('lines')]
    public function testGeneratedFormsAreFoundButNeverBlock(string $language, string $line): void
    {
        $result = ChatLineReviewer::create(__DIR__ . '/../../../config', $language)->review($line);

        $this->assertContains($result->getDecision(), ['review', 'reject'], $line);
    }
}
