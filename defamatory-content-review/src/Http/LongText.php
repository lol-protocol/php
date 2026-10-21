<?php

namespace DefamatoryContentReview\Http;

use DefamatoryContentReview\Chat\ChatLineResult;

/**
 * Un texto largo (una biografía, una nota) revisado por partes de hasta PIECE_CHARS:
 * párrafos enteros (separados por una línea en blanco), juntos mientras quepan, y
 * si un párrafo pasa de PIECE_CHARS, por oraciones; una oración más larga se
 * corta en el último espacio antes del límite, de cualquier tipo: antes sólo valía el
 * ASCII, y con saltos de línea, tabuladores o espacios no separables entre palabras el
 * corte era a la fuerza y partía un insulto («pu | ta»). Cada parte se revisa sola, y
 * también la costura de cada corte (LongTextSeam); el resultado junta todos los
 * hallazgos (una repetición en otro párrafo cuenta otra vez) con la decisión más grave.
 * Lo usa ModerationEndpoint.
 */
final class LongText
{
    public const PIECE_CHARS = 2000;
    private const ORDER = ['approve' => 0, 'review' => 1, 'reject' => 2];

    /** @param callable(string):ChatLineResult $review revisa una parte */
    public static function review(string $text, callable $review): ChatLineResult
    {
        $matches = [];
        $decision = 'approve';
        $parts = self::split($text);
        foreach ($parts as $i => [$piece, $joiner]) {
            $results = [$review($piece)];
            if (isset($parts[$i + 1])) {
                $results[] = LongTextSeam::review($piece, $joiner, $parts[$i + 1][0], $review);
            }
            foreach (array_filter($results) as $result) {
                $matches = [...$matches, ...$result->getMatches()];
                $decision = self::ORDER[$result->getDecision()] > self::ORDER[$decision] ? $result->getDecision() : $decision;
            }
        }

        return new ChatLineResult($text, $matches, $decision);
    }

    /**
     * @return list<string> las partes; ninguna pasa de PIECE_CHARS. Un texto corto es una sola parte, y un
     * párrafo termina en una línea en blanco: un salto suelto no corta («pu⏎ta» se sigue leyendo entero).
     */
    public static function pieces(string $text): array
    {
        return array_column(self::split($text), 0);
    }

    /**
     * Las partes, cada una con lo que la une a la siguiente en su costura: un espacio si el
     * corte cayó entre palabras, nada si partió una palabra a la fuerza (no había ningún espacio).
     *
     * @return list<array{0:string,1:string}>
     */
    private static function split(string $text): array
    {
        if (mb_strlen($text) <= self::PIECE_CHARS) {
            return [[$text, '']];
        }
        // Párrafos seguidos comparten parte hasta el límite: cada parte se revisa como un texto
        // corto, y una biografía de párrafos breves no paga una revisión (y una costura) por párrafo.
        $parts = [];
        $current = '';
        foreach (preg_split('/\R\s*\R/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $paragraph) {
            $sentences = mb_strlen($paragraph) <= self::PIECE_CHARS ? [$paragraph] : (preg_split('/(?<=[.!?…])\s+/u', $paragraph) ?: [$paragraph]);
            $glue = "\n\n";
            foreach ($sentences as $sentence) {
                if ($current !== '' && mb_strlen($current . $glue . $sentence) > self::PIECE_CHARS) {
                    $parts[] = [$current, ' '];
                    $current = '';
                }
                $current = $current === '' ? $sentence : $current . $glue . $sentence;
                $glue = ' ';
                while (mb_strlen($current) > self::PIECE_CHARS) {
                    $head = mb_substr($current, 0, self::PIECE_CHARS);
                    $space = preg_match('/^.+\s/su', $head, $m) === 1 ? mb_strlen($m[0]) - 1 : 0; // \s con /u: cualquier espacio
                    $cut = $space > 0 ? $space : self::PIECE_CHARS;
                    $parts[] = [mb_substr($current, 0, $cut), $space > 0 ? ' ' : ''];
                    $current = (string) preg_replace('/^\s+/u', '', mb_substr($current, $cut));
                }
            }
        }
        if (preg_match('/\S/u', $current) === 1) {
            $parts[] = [$current, ''];
        }

        return $parts;
    }
}
