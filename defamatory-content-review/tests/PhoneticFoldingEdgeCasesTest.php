<?php

namespace Tests;

use DefamatoryContentReview\FrenchPhoneticFolder;
use DefamatoryContentReview\GermanPhoneticFolder;
use DefamatoryContentReview\ItalianPhoneticFolder;
use DefamatoryContentReview\PortuguesePhoneticFolder;
use PHPUnit\Framework\TestCase;

/**
 * Casos donde el plegado NO debe confundir dos sonidos distintos, y casos
 * de forma plegada exacta. Las equivalencias simples viven en
 * PhoneticFoldingRulesTest — separado para que ningún archivo pase de
 * 100 líneas.
 */
class PhoneticFoldingEdgeCasesTest extends TestCase
{
    /** Sonidos distintos que el plegado no debe confundir. */
    public static function distinctSpellings(): array
    {
        return [
            // "chat" (gato) no debe sonar como "cat" con una c(a) suelta.
            'fra: el dígrafo ch se protege' => [FrenchPhoneticFolder::class, 'chat', 'cat'],
            'ita: b y v no se confunden' => [ItalianPhoneticFolder::class, 'vino', 'bino'],
            // El italiano no tiene la confusión s/z del español y el portugués.
            'ita: s y z no se confunden' => [ItalianPhoneticFolder::class, 'mezzo', 'messo'],
            // A diferencia del español, en portugués b/v son sonidos distintos.
            'por: b y v no se confunden' => [PortuguesePhoneticFolder::class, 'vaca', 'baca'],
            // Sin proteger lh, "filho" (hijo) colisionaría con "filo".
            'por: el dígrafo lh se protege' => [PortuguesePhoneticFolder::class, 'filho', 'filo'],
        ];
    }

    /** Forma plegada exacta. */
    public static function exactFolds(): array
    {
        return [
            'deu: ü se expande a ue' => [GermanPhoneticFolder::class, 'Müller', 'mueller'],
            'deu: ß se expande a ss' => [GermanPhoneticFolder::class, 'Straße', 'strasse'],
            // "Vater" suena /f/ y "Vase" /v/: sin diccionario de origen no se
            // puede distinguir un caso del otro, así que la v no se pliega.
            'deu: la v nativa no se toca' => [GermanPhoneticFolder::class, 'Vater', 'vater'],
            'ita: sólo quita acentos' => [ItalianPhoneticFolder::class, 'perché', 'perche'],
            'ita: sólo quita espacios y guiones' => [ItalianPhoneticFolder::class, 'Rossi-Bianchi', 'rossibianchi'],
            'por: ç suena siempre /s/' => [PortuguesePhoneticFolder::class, 'caça', 'casa'],
        ];
    }

    /** @dataProvider distinctSpellings */
    public function testFoldKeepsDistinctSoundsApart(string $folder, string $a, string $b): void
    {
        $this->assertNotSame($folder::fold($a), $folder::fold($b));
    }

    /** @dataProvider exactFolds */
    public function testFoldProducesExactForm(string $folder, string $input, string $expected): void
    {
        $this->assertSame($expected, $folder::fold($input));
    }
}
