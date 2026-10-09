<?php

namespace DefamatoryContentReview;

/**
 * Busca términos del diccionario en un texto, en dos pasos: (1) por ventanas
 * de 1 a 3 tokens, para entradas multipalabra ("hijo de puta") y la parte
 * ofensiva de un compuesto ("Jean-Cul" -> "cul"); (2) por palabra entera sin
 * separadores intercalados, para cerrar la evasión "pu-ta"/"pu.ta"/"pu'ta"
 * que el paso 1 no detecta (único camino para los 16 idiomas sin reglas
 * fonéticas).
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
        $split = PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE;
        $matches = self::scanWindows(preg_split(self::BETWEEN_WORDS, $text, -1, $split) ?: [], $search);

        foreach (preg_split('/\s+/u', $text, -1, $split) ?: [] as [$word, $wordStart]) {
            $glued = self::stripInsideWord($word);

            if ($glued === $word || ($found = $search($glued)) === null) {
                continue;
            }

            $wordEnd = $wordStart + strlen($word);

            // Si el paso 1 ya marcó algo que se solapa por posición con esta
            // palabra, es la misma señal contada dos veces. Comparar por
            // posición (no por texto) evita que un término corto de OTRA
            // palabra apague por casualidad de texto uno más grave aquí
            // (p. ej. "rata" en "esa rata" vs. "negrata" en "ne-grata").
            foreach ($matches as $match) {
                if ($match['start'] < $wordEnd && $match['end'] > $wordStart) {
                    continue 2;
                }
            }

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
            for ($span = min(3, $count - $i); $span >= 1; $span--) {
                $slice = array_slice($tokens, $i, $span);
                $phrase = implode(' ', array_column($slice, 0));
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
