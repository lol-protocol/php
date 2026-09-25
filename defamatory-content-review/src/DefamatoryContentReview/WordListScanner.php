<?php

namespace DefamatoryContentReview;

/**
 * Busca términos del diccionario dentro de un texto. Colaborador interno de
 * `WordList::findInText()` — separado porque son dos recorridos distintos
 * sobre el mismo texto, no una sola búsqueda.
 *
 * 1. Por ventanas de 1 a 3 tokens, para las entradas multipalabra ("hijo de
 *    puta") y para la parte ofensiva de un compuesto ("Jean-Cul" -> "cul").
 * 2. Por palabra entera sin separadores intercalados, porque si no
 *    escribir "pu-ta", "pu.ta" o "pu'ta" evade el filtro: el paso 1 parte
 *    por esos caracteres y busca "pu ta", que no es la clave de nada. Es la
 *    misma evasión que el plegado fonético ya cerraba, pero el camino
 *    literal —el único que tienen los 13 idiomas sin reglas fonéticas— la
 *    seguía teniendo abierta.
 */
final class WordListScanner
{
    /** Frontera entre tokens: espacios y la puntuación que separa palabras. */
    private const BETWEEN_WORDS = '/[\s\-.,_·]+/u';
    /** Separador intercalado dentro de una palabra: no parte un término. */
    private const INSIDE_WORD = '/[\-.,_·\'’]+/u';

    /**
     * Quita los separadores intercalados. Lo usa también
     * `WordList::normalize()`: si la clave del índice conservara el guion,
     * la entrada "half-breed" sería inalcanzable porque ninguna búsqueda
     * produce esa forma.
     */
    public static function stripInsideWord(string $word): string
    {
        return preg_replace(self::INSIDE_WORD, '', $word);
    }

    /**
     * @param callable(string):(array<string,mixed>|null) $search normalizado => datos del término
     * @return array<int,array<string,mixed>> cada coincidencia con su 'found'
     */
    public static function scan(string $text, callable $search): array
    {
        $matches = self::scanWindows(preg_split(self::BETWEEN_WORDS, $text, -1, PREG_SPLIT_NO_EMPTY) ?: [], $search);

        foreach (preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $glued = self::stripInsideWord($word);

            if ($glued === $word || ($found = $search($glued)) === null) {
                continue;
            }

            // Si el paso 1 ya marcó algo dentro de esta misma palabra, la
            // coincidencia pegada es la misma señal contada dos veces.
            foreach ($matches as $match) {
                if (mb_stripos($word, $match['found']) !== false) {
                    continue 2;
                }
            }

            $matches[] = $found + ['found' => $word];
        }

        return $matches;
    }

    /**
     * @param array<int,string> $tokens
     * @param callable(string):(array<string,mixed>|null) $search
     * @return array<int,array<string,mixed>>
     */
    private static function scanWindows(array $tokens, callable $search): array
    {
        $matches = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            for ($span = min(3, $count - $i); $span >= 1; $span--) {
                $phrase = implode(' ', array_slice($tokens, $i, $span));
                $found = $search($phrase);

                if ($found !== null) {
                    $matches[] = $found + ['found' => $phrase];
                    $i += $span - 1; // un token ya consumido por una frase larga no vuelve a contarse
                    break;
                }
            }
        }

        return $matches;
    }
}
