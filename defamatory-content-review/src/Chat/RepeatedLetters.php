<?php

namespace DefamatoryContentReview\Chat;

use DefamatoryContentReview\Normalization\AccentFolding;

/**
 * Letras repetidas como evasión: «puuuuta», «puuta», «mmmierda». El front
 * deja escribir como mucho 2 letras iguales seguidas
 * (js/limit-repeated-letters.js), pero el servidor no puede fiarse de eso.
 *
 * Cada racha puede valer 1 letra o 2 (ver RepeatedReadings): reducirlas todas
 * a 1 perdería las dobles legítimas del propio insulto («follarr» → «folar»).
 *
 * Tres o más iguales seguidas no existen en español ni en inglés; dos sí
 * («calle», «Pratt», «looser»), y reducirlas puede convertir una palabra o un
 * apellido legítimo en un insulto («calle» → «calé»). Por eso el hallazgo que
 * sólo existe reduciendo dobles se marca `repeat => doubled` (ChatLineReviewer
 * lo rebaja a revisión), y cada idioma lista en `legit` las palabras que
 * nunca deben leerse reducidas. Colaborador interno de ChatTopics.
 */
final class RepeatedLetters
{
    /** Minúsculas y sin tildes: la clave con la que se compara contra `legit`. */
    public static function key(string $word): string
    {
        return AccentFolding::fold(mb_strtolower($word));
    }

    /**
     * La misma búsqueda, pero si la frase no está tal cual prueba sus lecturas
     * con las rachas reducidas; el hallazgo trae `repeat`: `elongated` si la
     * frase tenía una racha de 3 o más, `doubled` si sólo hizo falta reducir dobles.
     *
     * @param callable(string):?array<string,mixed> $search
     * @param array<string,true> $legit
     * @return callable(string):?array<string,mixed>
     */
    public static function searcher(callable $search, array $legit): callable
    {
        return function (string $phrase) use ($search, $legit): ?array {
            if (($entry = $search($phrase)) !== null) {
                return $entry;
            }
            [$readings, $longest] = RepeatedReadings::of($phrase, $legit);
            foreach ($readings as $reading) {
                if (($entry = $search($reading)) !== null) {
                    return $entry + ['repeat' => $longest >= 3 ? 'elongated' : 'doubled'];
                }
            }

            return null;
        };
    }

    /**
     * El patrón con cada letra literal admitiendo repeticiones («matar» → «m+a+t+a+r+»), para
     * que «mataaaar» también lo cumpla. No toca lo escapado («\b», «\s»), las clases de
     * caracteres ni las letras que ya llevan cuantificador («s?»). No admite banderas ni
     * grupos con nombre: «(?i)» o «(?P<x>…)» se romperían.
     */
    public static function tolerant(string $pattern): string
    {
        $out = '';
        $inClass = false;
        for ($i = 0, $length = strlen($pattern); $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '\\') {
                $out .= $char . ($pattern[++$i] ?? '');
                continue;
            }
            $inClass = $char === '[' ? true : ($char === ']' ? false : $inClass);
            $out .= $char;
            if (!$inClass && ctype_alpha($char) && !str_contains('?+*{', $pattern[$i + 1] ?? ' ')) {
                $out .= '+';
            }
        }

        return $out;
    }
}
