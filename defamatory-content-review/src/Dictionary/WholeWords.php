<?php

namespace DefamatoryContentReview\Dictionary;

use DefamatoryContentReview\Normalization\HackerSpelling;

/**
 * Segundo paso de WordListScanner: cada palabra entera (hasta el espacio),
 * leída sin separadores intercalados («pu-ta», «pu.ta», «p~u~t~a») o con las
 * letras que dibujan los símbolos («p|_|ta», «1d!0t4», «N.4.Z.|»). Cierra la
 * evasión que la búsqueda por ventanas de palabras no ve.
 */
final class WholeWords
{
    /**
     * @param callable(string):(array<string,mixed>|null) $search
     * @param array<int,array<string,mixed>> $matches los del paso 1, con 'start' y 'end'
     * @return array<int,array<string,mixed>>
     */
    public static function apply(string $text, callable $search, array $matches): array
    {
        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE) ?: [] as [$word, $wordStart]) {
            $wordStart += strlen($word) - strlen($word = ltrim($word, '-.,_')); // «rata,» es «rata»: sólo cuenta lo de adentro
            $found = null; // sin separadores, o leída con símbolos de hacker («p~u~t~a», «1d!0t4»)
            foreach (array_unique([WordListScanner::stripInsideWord($word = rtrim($word, '-.,_')), ...HackerSpelling::readings($word)]) as $reading) {
                if ($reading !== $word && ($found = $search($reading)) !== null) {
                    break;
                }
            }
            if ($found === null) {
                continue;
            }
            $wordEnd = $wordStart + strlen($word);
            // Lo que el paso 1 marcó dentro de esta palabra es una parte de ella: gana la palabra entera
            // («lèche-cul», no «cul»), salvo que sea el mismo término o una frase más larga ya la cubra.
            // Por posición, no por texto: «rata» en «esa rata» no apaga «negrata» en «ne-grata».
            $overlapping = array_filter($matches, fn(array $m): bool => $m['start'] < $wordEnd && $m['end'] > $wordStart);
            foreach ($overlapping as $match) {
                if ($match['start'] < $wordStart || $match['end'] > $wordEnd || $match['original'] === $found['original']) {
                    continue 2;
                }
            }
            $matches = array_diff_key($matches, $overlapping);

            $matches[] = $found + ['found' => $word, 'start' => $wordStart, 'end' => $wordEnd];
        }

        return $matches;
    }
}
