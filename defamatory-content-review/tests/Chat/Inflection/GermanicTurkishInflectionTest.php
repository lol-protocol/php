<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Formas regulares del alemán, el neerlandés y el turco (GermanInflection, DutchInflection, TurkishInflection). */
class GermanicTurkishInflectionTest extends TestCase
{
    /** @return array<string,array{string,string,string,array<string,string>}> idioma, lema, tipo, forma => rasgo */
    public static function forms(): array
    {
        return [
            'alemán -e' => ['deu', 'hure', 'noun', ['hure' => 'sg', 'huren' => 'pl']],
            'alemán femenino -in' => ['deu', 'äffin', 'noun', ['äffin' => 'sg', 'äffinnen' => 'pl']],
            'alemán: Schwein no es femenino' => ['deu', 'schwein', 'noun', ['schwein' => 'sg']],
            'alemán débil' => ['deu', 'idiot', 'noun', ['idiot' => 'sg', 'idioten' => 'pl']],
            'alemán: cambia la vocal, va a mano' => ['deu', 'arsch', 'noun', ['arsch' => 'sg']],
            'alemán adjetivo' => ['deu', 'dumm', 'adj', ['dumm' => 'sg', 'dumme' => 'infl', 'dummer' => 'infl', 'dummen' => 'infl', 'dummes' => 'infl', 'dummem' => 'infl']],
            'neerlandés vocal corta' => ['nld', 'kut', 'noun', ['kut' => 'sg', 'kutten' => 'pl']],
            'neerlandés vocal larga' => ['nld', 'aap', 'noun', ['aap' => 'sg', 'apen' => 'pl']],
            'neerlandés f → v' => ['nld', 'teef', 'noun', ['teef' => 'sg', 'teven' => 'pl']],
            'neerlandés -er → -s' => ['nld', 'flikker', 'noun', ['flikker' => 'sg', 'flikkers' => 'pl']],
            'neerlandés vocal final' => ['nld', 'homo', 'noun', ['homo' => 'sg', "homo's" => 'pl']],
            'neerlandés io' => ['nld', 'idioot', 'noun', ['idioot' => 'sg', 'idioten' => 'pl']],
            'neerlandés adjetivo' => ['nld', 'dom', 'adj', ['dom' => 'sg', 'domme' => 'infl']],
            'neerlandés adjetivo átono' => ['nld', 'mager', 'adj', ['mager' => 'sg', 'magere' => 'infl']],
            'neerlandés s → z' => ['nld', 'vies', 'adj', ['vies' => 'sg', 'vieze' => 'infl']],
        ];
    }

    /** @param array<string,string> $expected */
    #[DataProvider('forms')]
    public function testForms(string $language, string $lemma, string $kind, array $expected): void
    {
        $this->assertSame($expected, TopicInflector::tagged($language, $lemma, $kind));
    }

    /** @return array<string,array{string,string,string}> */
    public static function lines(): array
    {
        return [
            'ihr Huren' => ['deu', 'ihr Huren', 'review'],
            'du dummes Schwein' => ['deu', 'du dummes Schwein', 'review'],
            'jullie hoeren' => ['nld', 'jullie hoeren', 'review'],
            'orospular' => ['tur', 'orospular', 'review'],
        ];
    }

    /** Lo generado sin revisión nativa llega como mucho a revisión (medium), aunque el lema bloquee. */
    #[DataProvider('lines')]
    public function testGeneratedFormsGoToReview(string $language, string $line, string $decision): void
    {
        $this->assertSame($decision, ChatLineReviewer::create(__DIR__ . '/../../../config', $language)->review($line)->getDecision());
    }
}
