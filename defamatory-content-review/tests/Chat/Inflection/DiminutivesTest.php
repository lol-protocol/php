<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Chat\Inflection\{Diminutives, FormSeverity};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Diminutivos («putita» degrada más, «cerdita» es cariñoso) y plurales de los insultos sexualizados en por/ita/fra. */
class DiminutivesTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../../config';

    public function testRegularDiminutives(): void
    {
        $this->assertSame(['putita' => 'dim.sg', 'putitas' => 'dim.pl'], Diminutives::tagged('spa', 'puta', 'noun'));
        $this->assertSame(['loquito' => 'dim.m.sg', 'loquitos' => 'dim.m.pl', 'loquita' => 'dim.f.sg', 'loquitas' => 'dim.f.pl'], Diminutives::tagged('spa', 'loco', 'adj'));
        $this->assertArrayHasKey('mujercita', Diminutives::tagged('spa', 'mujer', 'noun'), 'sin vocal final, los dos géneros');
        $this->assertSame(['putinha' => 'dim.sg', 'putinhas' => 'dim.pl'], Diminutives::tagged('por', 'puta', 'noun'));
        $this->assertArrayHasKey('porquinha', Diminutives::tagged('por', 'porco', 'adj'));
        $this->assertArrayHasKey('ladrãozinho', Diminutives::tagged('por', 'ladrão', 'noun'));
        $this->assertSame([], Diminutives::tagged('ita', 'puttana', 'noun'), 'no regular: va en also');
    }

    public function testADiminutiveOnlyExistsIfByAsksForIt(): void
    {
        $this->assertSame('none', FormSeverity::of('dim.f.sg', ['f' => 'high'], 'medium'), 'el femenino no arrastra al diminutivo');
        $this->assertSame('high', FormSeverity::of('dim.sg', ['dim' => 'high'], 'high'));
        $this->assertSame('low', FormSeverity::of('dim.f.pl', ['dim' => 'high', 'dim.f' => 'low'], 'high'));
    }

    /** @return array<string,array{string,string,string}> */
    public static function lines(): array
    {
        return [
            'putita' => ['spa', 'eres una putita', 'reject'],
            'zorrita' => ['spa', 'menuda zorrita', 'reject'],
            'cerdita: cariñoso, no se genera' => ['spa', 'mi cerdita', 'approve'],
            'putinha' => ['por', 'sua putinha', 'reject'],
            'putas (por)' => ['por', 'são umas putas', 'reject'],
            'puttane' => ['ita', 'siete delle puttane', 'reject'],
            'troie' => ['ita', 'quelle troie', 'reject'],
            'salopes' => ['fra', 'bande de salopes', 'reject'],
            'putes' => ['fra', 'sales putes', 'reject'],
        ];
    }

    #[DataProvider('lines')]
    public function testInTheChat(string $language, string $line, string $decision): void
    {
        $this->assertSame($decision, ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line)->getDecision(), $line);
    }
}
