<?php

namespace DefamatoryContentReview\Http;

use DefamatoryContentReview\Chat\ChatLineResult;

/**
 * Un texto largo (una biografía, una nota) revisado por partes: por párrafos (separados
 * por una línea en blanco) y,
 * si un párrafo pasa de PIECE_CHARS, por oraciones; una oración más larga se
 * corta en el último espacio antes del límite. Cada parte se revisa sola y el
 * resultado junta todos los hallazgos (una repetición en otro párrafo cuenta
 * otra vez) con la decisión más grave. Lo usa ModerationEndpoint.
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
        foreach (self::pieces($text) as $piece) {
            $result = $review($piece);
            $matches = [...$matches, ...$result->getMatches()];
            $decision = self::ORDER[$result->getDecision()] > self::ORDER[$decision] ? $result->getDecision() : $decision;
        }

        return new ChatLineResult($text, $matches, $decision);
    }

    /**
     * @return list<string> las partes; ninguna pasa de PIECE_CHARS. Un texto corto es una sola parte, y un
     * párrafo termina en una línea en blanco: un salto suelto no corta («pu⏎ta» se sigue leyendo entero).
     */
    public static function pieces(string $text): array
    {
        if (mb_strlen($text) <= self::PIECE_CHARS) {
            return [$text];
        }
        $pieces = [];
        foreach (preg_split('/\R\s*\R/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $paragraph) {
            $sentences = mb_strlen($paragraph) <= self::PIECE_CHARS ? [$paragraph] : (preg_split('/(?<=[.!?…])\s+/u', $paragraph) ?: [$paragraph]);
            $current = '';
            foreach ($sentences as $sentence) {
                if ($current !== '' && mb_strlen($current . ' ' . $sentence) > self::PIECE_CHARS) {
                    $pieces[] = $current;
                    $current = '';
                }
                $current = $current === '' ? $sentence : $current . ' ' . $sentence;
                while (mb_strlen($current) > self::PIECE_CHARS) {
                    $head = mb_substr($current, 0, self::PIECE_CHARS);
                    $cut = mb_strrpos($head, ' ') ?: self::PIECE_CHARS; // en el último espacio, o a la fuerza si no hay
                    $pieces[] = mb_substr($current, 0, $cut);
                    $current = ltrim(mb_substr($current, $cut));
                }
            }
            if (trim($current) !== '') {
                $pieces[] = $current;
            }
        }

        return $pieces;
    }
}
