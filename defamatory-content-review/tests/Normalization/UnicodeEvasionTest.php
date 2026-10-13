<?php

namespace Tests\Normalization;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\Normalization\EvasionCleaner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Trucos de Unicode que no cambian cómo se ve un insulto (EvasionCleaner). */
class UnicodeEvasionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string}> */
    public static function disguisedInsults(): array
    {
        return [
            'p cirílica' => ["\u{0440}uta"],
            'a cirílica' => ["put\u{0430}"],
            'ómicron griega' => ["idi\u{03BF}ta"],
            'espacio de ancho cero' => ["pu\u{200B}ta"],
            'guion blando' => ["pu\u{00AD}ta"],
            'ancho completo' => ['ｐｕｔａ'],
            'negrita matemática' => ['𝐩𝐮𝐭𝐚'],
            'emoji en medio' => ['pu🙂ta'],
            'selector de variante' => ["puta\u{FE0F}"],
            'marca de dirección' => ["pu\u{200E}ta"],
        ];
    }

    #[DataProvider('disguisedInsults')]
    public function testChatSeesThroughTheDisguise(string $line): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review("eres una {$line}");

        $this->assertNotSame('approve', $result->getDecision(), $line);
        $this->assertStringNotContainsString($line, $result->censored(), 'se censura lo que se escribió');
    }

    #[DataProvider('disguisedInsults')]
    public function testNamesSeeThroughTheDisguise(string $word): void
    {
        $this->assertFalse(DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa')->validateName($word)->isValid(), $word);
    }

    public function testNameFusionSeesThroughTheDisguise(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');

        foreach ([["Elba", "G\u{0456}na"], ["Elba", "Gi\u{200B}na"], ['Ｅｌｂａ', 'Ｇｉｎａ']] as [$first, $last]) {
            $this->assertFalse($reviewer->validateFullName($first, $last)->isValid(), "{$first} {$last}");
        }
        $this->assertTrue($reviewer->validateFullName('José', 'Muñoz')->isValid());
    }

    public function testOtherScriptsAreLeftAlone(): void
    {
        $this->assertSame('свинья', EvasionCleaner::clean('свинья', 'rus'), 'ruso no se pasa a latín');
        $this->assertSame('Ελληνικά', EvasionCleaner::clean('Ελληνικά', 'ell'));
        $this->assertSame("क्\u{200D}ष", EvasionCleaner::clean("क्\u{200D}ष", 'hin'), 'el ZWJ une letras en devanagari');
        $this->assertSame('Muñoz Pérez', EvasionCleaner::clean('Muñoz Pérez', 'spa'));
        $this->assertSame('hola 👋 ¿qué tal?', EvasionCleaner::clean('hola 👋 ¿qué tal?', 'spa'), 'un emoji entre palabras se queda');
    }

    public function testRussianInsultsStillWork(): void
    {
        $this->assertNotSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, 'rus')->review('ты свинья')->getDecision());
    }
}
