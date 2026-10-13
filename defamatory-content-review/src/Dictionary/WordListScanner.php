<?php

namespace DefamatoryContentReview\Dictionary;

/**
 * Busca términos del diccionario en un texto, en dos pasos: (1) por ventanas
 * de 1 a 5 tokens, para entradas multipalabra ("hijo de puta") y la parte
 * ofensiva de un compuesto ("Jean-Cul" -> "cul"); (2) por palabra entera sin
 * separadores intercalados, para cerrar la evasión "pu-ta"/"pu.ta"/"pu'ta"
 * que el paso 1 no detecta (único camino para los 16 idiomas sin reglas
 * fonéticas). Las escrituras sin espacios se parten con WordSegmenter.
 */
final class WordListScanner
{
    /** Frontera entre tokens: espacios y la puntuación que separa palabras («¡idiota!», «(rata)», «puta?»). */
    private const BETWEEN_WORDS = '/[\s\-.,_·!¡?¿;:"“”„«»()\[\]{}…]+/u';
    /** Largo máximo de una entrada multipalabra («hijo de la gran puta»). */
    private const MAX_WORDS = 5;
    /** Separador intercalado dentro de una palabra: no parte un término. */
    private const INSIDE_WORD = '/[\-.,_·\'’]+/u';

    /** Quita los separadores intercalados. Lo usa también `WordList::normalize()`: si la clave del índice
     * conservara el guion, ninguna búsqueda llegaría a la entrada "half-breed". */
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
        $split = PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE;
        $tokens = array_merge([], ...array_map(WordSegmenter::split(...), preg_split(self::BETWEEN_WORDS, $text, -1, $split) ?: []));
        $matches = self::scanWindows($tokens, $search);

        foreach (preg_split('/\s+/u', $text, -1, $split) ?: [] as [$word, $wordStart]) {
            $wordStart += strlen($word) - strlen($word = ltrim($word, '-.,_')); // «rata,» es «rata»: sólo cuenta lo de adentro
            $glued = self::stripInsideWord($word = rtrim($word, '-.,_'));
            if ($glued === $word || ($found = $search($glued)) === null) {
                continue;
            }
            $wordEnd = $wordStart + strlen($word);

            // Lo que el paso 1 marcó dentro de esta palabra es una parte de ella:
            // la palabra entera gana («lèche-cul», no «cul»). Si una frase más
            // larga ya la cubre, se queda la frase. Se compara por posición, no
            // por texto: «rata» en «esa rata» no apaga «negrata» en «ne-grata».
            $overlapping = array_filter($matches, fn(array $m): bool => $m['start'] < $wordEnd && $m['end'] > $wordStart);
            foreach ($overlapping as $match) {
                if ($match['start'] < $wordStart || $match['end'] > $wordEnd) {
                    continue 2;
                }
            }
            $matches = array_diff_key($matches, $overlapping);

            $matches[] = $found + ['found' => $word, 'start' => $wordStart, 'end' => $wordEnd];
        }

        return array_map(static fn(array $match) => array_diff_key($match, ['start' => true, 'end' => true]), $matches);
    }

    /**
     * @param array<int,array{0:string,1:int}> $tokens texto y posición (offset capture)
     * @param callable(string):(array<string,mixed>|null) $search
     * @return array<int,array<string,mixed>> cada coincidencia incluye 'start' y 'end' (posición en el texto original)
     */
    private static function scanWindows(array $tokens, callable $search): array
    {
        $matches = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            for ($span = min(self::MAX_WORDS, $count - $i); $span >= 1; $span--) {
                $slice = array_slice($tokens, $i, $span);
                $phrase = $slice[0][0];
                for ($k = 1; $k < $span; $k++) { // pegadas si eran contiguas (japonés, tailandés…), con espacio si no
                    $phrase .= ($slice[$k - 1][1] + strlen($slice[$k - 1][0]) === $slice[$k][1] ? '' : ' ') . $slice[$k][0];
                }
                $found = $search($phrase);

                if ($found !== null) {
                    $last = $slice[count($slice) - 1];
                    $matches[] = $found + [
                        'found' => $phrase,
                        'start' => $slice[0][1],
                        'end' => $last[1] + strlen($last[0]),
                    ];
                    $i += $span - 1; // un token ya consumido por una frase larga no vuelve a contarse
                    break;
                }
            }
        }

        return $matches;
    }
}
