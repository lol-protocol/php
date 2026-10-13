<?php

namespace DefamatoryContentReview\Normalization;

/**
 * Deshace los trucos de Unicode que dejan pasar un insulto sin cambiar cómo
 * se ve: «рuta» con «р» cirílica, «pu​ta» con un espacio de ancho cero,
 * «ｐｕｔａ» de ancho completo, «𝐩𝐮𝐭𝐚» en negrita matemática, «pu🙂ta» con un
 * emoji en medio, «puta️» con un selector de variante. Lo usan
 * WordList::normalize() (nombres y chat) y ChatPatternMatcher::fold() (amenazas).
 *
 * Los parecidos (cirílico y griego que se ven latinos) sólo se cambian en una
 * palabra que mezcla escrituras, o en un idioma de alfabeto latino si la
 * palabra entera está hecha de letras parecidas: «свинья» en ruso no se toca.
 */
final class EvasionCleaner
{
    private const NON_LATIN = ['rus', 'ukr', 'bul', 'ell', 'ara', 'heb', 'hin', 'jpn', 'kor', 'yue', 'tha'];
    private const LOOKALIKES = [
        'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'у' => 'y', 'х' => 'x', 'і' => 'i', 'ј' => 'j',
        'ѕ' => 's', 'ԁ' => 'd', 'һ' => 'h', 'ӏ' => 'l', 'к' => 'k', 'м' => 'm', 'ԛ' => 'q', 'ԝ' => 'w',
        'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M', 'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C',
        'Т' => 'T', 'У' => 'Y', 'Х' => 'X', 'І' => 'I', 'Ј' => 'J', 'Ѕ' => 'S',
        'α' => 'a', 'ε' => 'e', 'ι' => 'i', 'κ' => 'k', 'ν' => 'v', 'ο' => 'o', 'ρ' => 'p', 'υ' => 'u',
        'Α' => 'A', 'Β' => 'B', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Ι' => 'I', 'Κ' => 'K', 'Μ' => 'M', 'Ν' => 'N',
        'Ο' => 'O', 'Ρ' => 'P', 'Τ' => 'T', 'Υ' => 'Y', 'Χ' => 'X',
    ];
    /** Invisibles (formato, selectores de variante); ZWJ y ZWNJ aparte: unen letras en árabe o devanagari. */
    private const INVISIBLE = '/[^\P{Cf}\x{200C}\x{200D}]|[\x{FE00}-\x{FE0F}\x{E0100}-\x{E01EF}]/u';
    private const JOINER_IN_LATIN = '/(?<=\p{Latin})[\x{200C}\x{200D}]+(?=\p{Latin})/u';
    /** Emoji y símbolos metidos entre dos letras: «pu🙂ta», «pu´ta». */
    private const SYMBOL_INSIDE = '/(?<=\p{L})[\p{So}\p{Sk}\x{200D}]+(?=\p{L})/u';
    private const STYLED = '/[\x{FF01}-\x{FF5E}\x{1D400}-\x{1D6A3}\x{1D7CE}-\x{1D7FF}]/u';

    public static function clean(string $text, string $language = ''): string
    {
        if (preg_match('/[^\x00-\x7F]/', $text) !== 1) {
            return $text; // ASCII puro: nada que deshacer
        }
        $text = preg_replace_callback(self::STYLED, fn(array $m): string => self::unstyle(mb_ord($m[0])), $text) ?? $text;
        if (class_exists(\Normalizer::class) && !\Normalizer::isNormalized($text, \Normalizer::FORM_KC)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
        }
        if (preg_match('/[\p{Cf}\p{So}\p{Sk}\x{FE00}-\x{FE0F}]/u', $text) === 1) {
            $text = preg_replace([self::INVISIBLE, self::JOINER_IN_LATIN, self::SYMBOL_INSIDE], '', $text) ?? $text;
        }
        $latin = !in_array($language, self::NON_LATIN, true);
        if (preg_match('/[\p{Cyrillic}\p{Greek}]/u', $text) !== 1 || (!$latin && preg_match('/\p{Latin}/u', $text) !== 1)) {
            return $text; // nada que pueda ser un parecido
        }

        return preg_replace_callback('/[\p{L}\p{M}]+/u', function (array $m) use ($latin): string {
            $word = $m[0];
            $mixed = preg_match('/\p{Latin}/u', $word) === 1 && preg_match('/[\p{Cyrillic}\p{Greek}]/u', $word) === 1;
            $allLookalike = $latin && strtr($word, self::LOOKALIKES) !== $word
                && preg_match('/[^\p{Latin}\p{M}]/u', strtr($word, self::LOOKALIKES)) !== 1;

            return $mixed || $allLookalike ? strtr($word, self::LOOKALIKES) : $word;
        }, $text) ?? $text;
    }

    /** Ancho completo y alfanuméricos matemáticos (negrita, cursiva, gótica…) a su letra o dígito. */
    private static function unstyle(int $code): string
    {
        return match (true) {
            $code <= 0xFF5E => chr($code - 0xFF01 + 0x21),
            $code >= 0x1D7CE => (string) (($code - 0x1D7CE) % 10),
            default => chr((($code - 0x1D400) % 52) < 26 ? 0x41 + ($code - 0x1D400) % 52 : 0x61 + ($code - 0x1D400) % 52 - 26),
        };
    }
}
