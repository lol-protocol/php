<?php

namespace Tests\Http;

use DefamatoryContentReview\Chat\ChatLineResult;
use DefamatoryContentReview\Http\LongText;
use DefamatoryContentReview\Http\ModerationEndpoint;
use PHPUnit\Framework\TestCase;

/**
 * Lo que cae sobre un corte de LongText: antes, por los dos huecos que deja partir, se
 * aprobaba el texto entero.
 */
class LongTextCutsTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** Sólo se cortaba en el espacio ASCII: con otros separadores el corte partía el insulto en «pu» | «ta». */
    public function testAnyKindOfSpaceIsACutPoint(): void
    {
        foreach (["\n" => 'salto de línea', "\t" => 'tabulador', "\u{00A0}" => 'espacio no separable', "\u{3000}" => 'espacio ideográfico'] as $space => $name) {
            $text = self::endingAt(1998, $space) . 'puta' . $space . str_repeat('casa' . $space, 30);

            $this->assertStringStartsWith('puta', LongText::pieces($text)[1], "con {$name}, el corte no parte la palabra");
            $this->assertSame('reject', $this->decide($text), "con {$name}");
        }
    }

    /** Cortadas en el espacio que las separa, cada mitad caía en una parte y ninguna decía nada. */
    public function testAPhraseAcrossACutIsFoundInTheSeam(): void
    {
        foreach (['heil hitler', 'white power', 'concha de tu madre'] as $phrase) {
            $first = explode(' ', $phrase)[0];
            $text = self::endingAt(1999 - mb_strlen($first), ' ') . $phrase . ' ' . str_repeat('casa ', 30);

            $this->assertStringEndsWith($first, LongText::pieces($text)[0], 'la frase cae sobre el corte');
            $this->assertSame('approve', $this->decide("casa {$first} casa"), 'cada palabra sola es inocente');
            $this->assertSame('reject', $this->decide($text), $phrase);
        }
    }

    /** Lo que ya está entero en una parte, cerca del corte, entra también en la costura: no cuenta dos veces. */
    public function testWhatAPieceAlreadyHoldsIsNotCountedAgain(): void
    {
        $text = self::endingAt(1990 - mb_strlen('hijo de puta'), ' ') . 'hijo de puta ' . str_repeat('casa ', 400);
        $response = (new ModerationEndpoint(self::CONFIG_DIR))->handle('POST', (string) json_encode(['text' => $text]));

        $this->assertSame(['hijo de puta'], array_column($response['body']['matches'], 'term'));
    }

    /** Sin ningún espacio el corte parte la palabra, y la costura la junta sin agregarle uno. */
    public function testAForcedCutIsRejoinedWithoutASpace(): void
    {
        $text = str_repeat('x', 1998) . 'puta' . str_repeat('x', 100);
        $review = function (string $piece): ChatLineResult {
            $matches = str_contains($piece, 'puta') ? [['found' => 'puta', 'riskType' => 'ordinario', 'contentType' => 'difamatorio']] : [];

            return new ChatLineResult($piece, $matches, $matches === [] ? 'approve' : 'reject');
        };

        $this->assertSame([], array_filter(LongText::pieces($text), fn(string $p) => str_contains($p, 'puta')), 'ninguna parte la tiene entera');
        $this->assertSame('reject', LongText::review($text, $review)->getDecision());
    }

    private function decide(string $text): string
    {
        return (new ModerationEndpoint(self::CONFIG_DIR))->handle('POST', (string) json_encode(['text' => $text]))['body']['decision'];
    }

    /** Relleno inocente de $length caracteres que termina en $space. */
    private static function endingAt(int $length, string $space): string
    {
        $text = '';
        while (mb_strlen($text) + 5 <= $length - 1) {
            $text .= 'casa' . $space;
        }

        return $text . str_repeat('a', $length - mb_strlen($text) - 1) . $space;
    }
}
