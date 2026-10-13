<?php

namespace DefamatoryContentReview\Normalization;

/**
 * Deshace los trucos de Unicode que dejan pasar un insulto sin cambiar cómo
 * se ve: «рuta» con «р» cirílica, «pu​ta» con un espacio de ancho cero,
 * «ｐｕｔａ» de ancho completo, «𝐩𝐮𝐭𝐚» en negrita matemática, «pu🙂ta» con un
 * emoji en medio, «puta️» con un selector de variante, «🅿🆄🆃🅰» (StyledLetters), «pu<b></b>ta»,
 * «pu&#116;a», «\u{202E}atup» (se ve «puta») o «ɐʇnd» (UpsideDown). Lo usan
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
    /** Etiquetas HTML: dentro de una palabra se quitan («pu<b></b>ta»), entre palabras separan. */
    private const TAG = '/<\/?[a-z][^<>]{0,80}>/i';
    /** Marcas que siguen en una letra ASCII después de NFKC no forman ninguna letra: «p̶u̶t̶a̶», «p̲u̲t̲a̲», texto zalgo. */
    private const MARKS_ON_ASCII = '/(?<=[A-Za-z])\p{Mn}+/u';
    /** Forzado de derecha a izquierda: «\u{202E}atup» se ve «puta». Vale hasta el cierre o el fin de línea. */
    private const RIGHT_TO_LEFT = '/\x{202E}([^\x{202A}-\x{202E}\x{2066}-\x{2069}\n]*)\x{202C}?/u';

    public static function clean(string $text, string $language = ''): string
    {
        $text = self::unmarkup(mb_scrub($text, 'UTF-8')); // un byte inválido apagaba todas las expresiones regulares
        if (preg_match('/[^\x00-\x7F]/', $text) !== 1) {
            return $text; // ASCII puro: nada que deshacer
        }
        $text = preg_replace_callback(self::RIGHT_TO_LEFT, fn(array $m): string => implode('', array_reverse(mb_str_split($m[1]))), $text) ?? $text;
        $text = StyledLetters::plain(str_replace('İ', 'I', UpsideDown::flip($text))); // «İBNE» en minúsculas sería «i» + punto combinante
        if (class_exists(\Normalizer::class) && !\Normalizer::isNormalized($text, \Normalizer::FORM_KC)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
        }
        $text = preg_replace(self::MARKS_ON_ASCII, '', $text) ?? $text;
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

    /** Etiquetas HTML y entidades («&#116;», «&eacute;»): el navegador las muestra como letras. */
    private static function unmarkup(string $text): string
    {
        if (str_contains($text, '<')) {
            $text = preg_replace(['/(?<=\p{L})(?:' . trim(self::TAG, '/i') . ')+(?=\p{L})/iu', self::TAG], ['', ' '], $text) ?? $text;
        }

        return preg_match('/&(?:#\d+|#x[0-9a-f]+|[a-z]+);/i', $text) === 1 ? html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $text;
    }
}
