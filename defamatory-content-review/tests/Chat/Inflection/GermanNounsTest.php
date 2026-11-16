<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Plural y genitivo de los sustantivos alemanes por terminación (GermanInflection), medidos contra UniMorph. */
class GermanNounsTest extends TestCase
{
    /** @return array<string,array{string,list<string>}> lema, formas que deben estar */
    public static function nouns(): array
    {
        return [
            '-e: plural en -n' => ['Hure', ['Huren']],
            '-in: plural en -nen' => ['Idiotin', ['Idiotinnen']],
            'débil (-ot): plural en -en' => ['Idiot', ['Idioten']],
            '-en: genitivo en -s' => ['Wagen', ['Wagens']],
            '-er: dativo plural y genitivo' => ['Wichser', ['Wichsern', 'Wichsers']],
            '-el: dativo plural y genitivo' => ['Esel', ['Eseln', 'Esels']],
            '-ung: plural en -en' => ['Beziehung', ['Beziehungen']],
            '-ion: plural en -en' => ['Animation', ['Animationen']],
            '-d: genitivo y plural en -e' => ['Hund', ['Hunds', 'Hunde', 'Hundes']],
            '-ch: genitivo y plural en -e' => ['Arsch', ['Arsches', 'Arsche']],
            '-t: varias terminaciones' => ['Dieb', ['Diebe', 'Diebes']],
            '-o: plural en -s' => ['Auto', ['Autos']],
            '-g: plural en -en' => ['Teig', ['Teigen']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('nouns')]
    public function testNounForms(string $lemma, array $expected): void
    {
        $forms = TopicInflector::forms('deu', $lemma, 'noun');

        $this->assertSame([], array_diff($expected, $forms), $lemma);
        $this->assertSame($lemma, $forms[0]);
        $this->assertSame($forms, array_values(array_unique($forms)));
    }

    public function testAdjectivesAndVerbsKeepTheirRules(): void
    {
        $this->assertContains('dummen', TopicInflector::forms('deu', 'dumm', 'adj'));
        $this->assertSame(['ficken'], TopicInflector::forms('deu', 'ficken', 'verb'));
    }
}
