<?php

namespace DefamatoryContentReview\Dictionary;

/**
 * Parte en palabras los tramos de escrituras que no separan con espacios
 * (japonés, chino/cantonés, tailandés, laosiano, jemer, birmano), con el
 * segmentador de ICU (`IntlBreakIterator`, extensión `intl`). Sin él, una
 * frase entera era un solo token y sólo se reconocía la palabra suelta:
 * «お前はバカだ» → お前 | は | バカ | だ.
 *
 * Sólo toca los tokens que contienen esas escrituras: el resto del texto se
 * parte como siempre. Sin la extensión `intl` devuelve el token entero, que es
 * el comportamiento anterior. Colaborador de WordListScanner.
 */
final class WordSegmenter
{
    public const UNSPACED = '\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}\p{Lao}\p{Khmer}\p{Myanmar}';

    public static function available(): bool
    {
        return class_exists(\IntlBreakIterator::class);
    }

    /**
     * @param array{0:string,1:int} $token texto y posición en bytes
     * @return array<int,array{0:string,1:int}> las palabras del token con su posición; los signos y espacios se descartan
     */
    public static function split(array $token): array
    {
        [$text, $offset] = $token;
        if (!self::available() || !preg_match('/[' . self::UNSPACED . ']/u', $text)) {
            return [$token];
        }

        $iterator = \IntlBreakIterator::createWordInstance('');
        $iterator->setText($text);
        $words = [];
        $start = 0;
        foreach ($iterator as $end) {
            if ($end === 0) {
                continue;
            }
            $word = substr($text, $start, $end - $start);
            if (preg_match('/[\p{L}\p{N}]/u', $word)) {
                $words[] = [$word, $offset + $start];
            }
            $start = $end;
        }

        return $words ?: [$token];
    }
}
