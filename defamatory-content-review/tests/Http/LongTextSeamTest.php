<?php

namespace Tests\Http;

use DefamatoryContentReview\Http\LongText;
use DefamatoryContentReview\Http\LongTextSeam;
use DefamatoryContentReview\Http\ModerationEndpoint;
use PHPUnit\Framework\TestCase;

/** LongTextSeam: lo que cruza un corte, venga de donde venga el corte, y lo ancho que es la ventana. */
class LongTextSeamTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /**
     * El corte puede caer en cualquier hueco de la frase, no sólo en el primero: se parte por cada uno.
     * Cada palabra suelta es inocente, así que sin la costura el texto entero se aprobaba.
     */
    public function testAPhraseIsFoundWhereverTheCutFallsInsideIt(): void
    {
        foreach (['concha de tu madre', 'heil hitler', 'white power', 'sieg heil'] as $phrase) {
            preg_match_all('/ /', $phrase, $gaps, PREG_OFFSET_CAPTURE);
            foreach ($gaps[0] as [, $offset]) {
                // El último espacio antes del carácter 2.000 es este hueco: queda en el índice 1.998.
                $text = self::endingAt(1998 - $offset, ' ') . $phrase . ' ' . str_repeat('casa ', 30);

                $this->assertSame(substr($phrase, 0, $offset), substr(LongText::pieces($text)[0], -$offset), "{$phrase}: el corte cae en el hueco {$offset}");
                $this->assertSame('reject', $this->decide($text), "{$phrase}, cortada en el hueco {$offset}");
            }
        }
    }

    /** La costura es más corta que una parte, y alcanza para la frase más larga de los diccionarios. */
    public function testTheSeamWindowCoversTheLongestDictionaryPhrase(): void
    {
        $longest = 0;
        foreach (glob(__DIR__ . '/../../config/languages/*.php') ?: [] as $file) {
            $config = require $file;
            array_walk_recursive($config, static function ($value, $key) use (&$longest): void {
                if ($key === 'word' && is_string($value)) {
                    $longest = max($longest, mb_strlen($value));
                }
            });
        }

        $this->assertGreaterThan(0, $longest);
        $this->assertGreaterThanOrEqual($longest, LongTextSeam::CHARS, 'una frase entera cabe de cada lado del corte');
        $this->assertLessThan(LongText::PIECE_CHARS, LongTextSeam::CHARS);
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
