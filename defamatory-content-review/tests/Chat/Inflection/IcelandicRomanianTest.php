<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** IcelandicInflection y RomanianInflection (medidas contra UniMorph con bin/inflection-check.php) y ArabicPlurals. */
class IcelandicRomanianTest extends TestCase
{
    use AssertsInflectedForms;

    /** @return array<string,array{string,string,string,list<string>}> idioma, lema, tipo, formas que deben estar */
    public static function forms(): array
    {
        return [
            'islandés -ur (masculino fuerte)' => ['isl', 'hundur', 'noun', ['hundi', 'hunda', 'hundar', 'hundum', 'hundinn', 'hundurinn', 'hunds']],
            'islandés -a (femenino débil)' => ['isl', 'skepna', 'noun', ['skepnu', 'skepnur', 'skepnan', 'skepnunni', 'skepnum']],
            'islandés -i (masculino débil)' => ['isl', 'fáviti', 'noun', ['fávita', 'fávitar', 'fávitum', 'fávitinn', 'fávitana']],
            'islandés femenino en -ing' => ['isl', 'ending', 'noun', ['endingin', 'endingar', 'endingu', 'endingum']],
            'rumano -t: artículo, vocativo y plural palatalizado' => ['ron', 'idiot', 'noun', ['idioți', 'idiotul', 'idiotului', 'idiotule', 'idioții']],
            'rumano -ă: plural indefinido y artículo' => ['ron', 'târfă', 'noun', ['târfa', 'târfe', 'târfei', 'târfele']],
            'rumano -e' => ['ron', 'câine', 'noun', ['câini', 'câinii', 'câinilor']],
            'rumano -u' => ['ron', 'dobitoc', 'noun', ['dobitoci', 'dobitocul', 'dobitocule']],
            'árabe: plurales rotos añadidos' => ['ara', 'مخنث', 'noun', ['مخانيث']],
            'rumano adjetivo -st' => ['ron', 'prost', 'adj', ['proști', 'prostă', 'prostul', 'prostului', 'proștilor']],
            'rumano adjetivo -t' => ['ron', 'tâmpit', 'adj', ['tâmpită', 'tâmpite', 'tâmpiți', 'tâmpitul', 'tâmpiților']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('forms')]
    public function testForms(string $language, string $lemma, string $kind, array $expected): void
    {
        $this->assertFormsInclude($language, $lemma, $kind, $expected, true);
    }

    public function testIcelandicAdjectivesAndVerbsStayLiteral(): void
    {
        $this->assertSame(['heimskur'], array_keys(TopicInflector::tagged('isl', 'heimskur', 'adj')));
        $this->assertSame(['bölva'], array_keys(TopicInflector::tagged('isl', 'bölva', 'verb')));
    }
}
