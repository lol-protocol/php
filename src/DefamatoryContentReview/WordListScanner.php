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
     * @param callable(string):?array $search normalizado => datos del término
     * @return array<int,array> cada coincidencia con su 'found'
     */
    public static function scan(string $text, callable $search): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Cada token recuerda de qué palabra salió, para poder decidir
        // después qué coincidencias caen enteras dentro de una misma.
        $tokens = [];
        foreach ($words as $index => $word) {
            foreach (preg_split(self::BETWEEN_WORDS, $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                $tokens[] = ['text' => $token, 'word' => $index];
            }
        }

        $matches = self::scanWindows($tokens, $search);

        foreach ($words as $index => $word) {
            $glued = self::stripInsideWord($word);

            if ($glued === $word || ($found = $search($glued)) === null) {
                continue;
            }

            // La palabra entera es un término del diccionario, así que
            // manda sobre los fragmentos que el paso 1 halló dentro de
            // ella: "pedó-filo" es "pedófilo" (high), no el "filo" (low)
            // que quedaba al partir por el guion. Antes ganaba el
            // fragmento —bastaba con intercalar un guion para bajarse la
            // severidad— porque una coincidencia previa contenida en la
            // palabra descartaba la pegada. Sólo se descartan las
            // coincidencias contenidas en ESTA palabra: una frase que la
            // cruza ("hijo de puta") abarca más y sigue valiendo.
            $matches = array_values(array_filter(
                $matches,
                fn(array $match) => $match['firstWord'] !== $index || $match['lastWord'] !== $index
            ));

            $matches[] = $found + [
                'found' => $word,
                'start' => self::firstTokenOf($tokens, $index),
                'firstWord' => $index,
                'lastWord' => $index,
            ];
        }

        // El orden de aparición importa: NameEvaluator usa la posición de
        // cada coincidencia para no colapsar dos apariciones distintas del
        // mismo término entre idiomas de una familia.
        usort($matches, fn(array $a, array $b) => $a['start'] <=> $b['start']);

        return array_map(
            fn(array $match) => array_diff_key($match, ['start' => null, 'firstWord' => null, 'lastWord' => null]),
            $matches
        );
    }

    /** @param array<int,array{text:string,word:int}> $tokens */
    private static function firstTokenOf(array $tokens, int $word): int
    {
        foreach ($tokens as $position => $token) {
            if ($token['word'] === $word) {
                return $position;
            }
        }

        return count($tokens);
    }

    /** @param array<int,array{text:string,word:int}> $tokens @param callable(string):?array $search */
    private static function scanWindows(array $tokens, callable $search): array
    {
        $matches = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            for ($span = min(3, $count - $i); $span >= 1; $span--) {
                $window = array_slice($tokens, $i, $span);
                $phrase = implode(' ', array_column($window, 'text'));
                $found = $search($phrase);

                if ($found !== null) {
                    $matches[] = $found + [
                        'found' => $phrase,
                        'start' => $i,
                        'firstWord' => $window[0]['word'],
                        'lastWord' => $window[$span - 1]['word'],
                    ];
                    $i += $span - 1; // un token ya consumido por una frase larga no vuelve a contarse
                    break;
                }
            }
        }

        return $matches;
    }
}
