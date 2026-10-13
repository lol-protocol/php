<?php

namespace DefamatoryContentReview\Normalization;

/**
 * Lecturas de una palabra escrita con símbolos para saltarse el filtro:
 * separadores que no son puntuación corriente («p~u~t~a», «p|u|t|a»,
 * «p(u)t(a)») y letras dibujadas con símbolos al estilo hacker («p|_|ta»,
 * «|\|4z1», «1d!0t4», «N.4.Z.|»). No reemplaza a Leetspeak: «!» y «|» sólo
 * se leen como letras dentro de una palabra, nunca en la línea entera (así
 * «¡hola!» no pasa a ser «ihola» ni rompe las frases con forma). Lo usa el
 * paso de palabra entera de WordListScanner.
 */
final class HackerSpelling
{
    /** Letras dibujadas con varios símbolos; van antes que los de uno solo. */
    private const DRAWN = [
        '|_|' => 'u', '|\\|' => 'n', '\\/\\/' => 'w', '\\/' => 'v', '/\\' => 'a', '|<' => 'k', '|)' => 'd',
        '|-|' => 'h', '|3' => 'b', '()' => 'o', '[]' => 'o',
    ];
    private const SINGLE = ['!' => 'i', '¡' => 'i', '|' => 'i', '€' => 'e', '£' => 'l', '¥' => 'y', '§' => 's'];
    /** Lo que no es letra, marca, dígito ni un símbolo que Leetspeak ya lee («@», «$»). */
    private const NOT_LETTER = '/[^\p{L}\p{M}\p{N}@$]+/u';

    /** @return list<string> lecturas posibles, sin repetir; vacía si la palabra no tiene símbolos */
    public static function readings(string $word): array
    {
        if (preg_match(self::NOT_LETTER, $word) !== 1) {
            return [];
        }
        $readings = [
            preg_replace(self::NOT_LETTER, '', $word) ?? '',                                   // «p~u~t~a» → «puta»
            preg_replace(self::NOT_LETTER, '', strtr(strtr($word, self::DRAWN), self::SINGLE)) ?? '', // «N.4.Z.|» → «N4Zi»
        ];

        return array_values(array_unique(array_filter($readings, fn(string $r): bool => mb_strlen($r) >= 2 && $r !== $word)));
    }
}
