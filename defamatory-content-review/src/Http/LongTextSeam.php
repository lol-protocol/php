<?php

namespace DefamatoryContentReview\Http;

use DefamatoryContentReview\Chat\ChatLineResult;

/**
 * La costura de un corte de LongText: el final de una parte unido al principio de la
 * siguiente. Sin ella, lo que cruza el corte no lo ve ninguna de las dos: «heil | hitler»
 * o «white | power», cuyas palabras sueltas son inocentes, aprobaban el texto entero.
 */
final class LongTextSeam
{
    /** Cuánto de cada lado entra: de sobra para el texto más largo de config/ (166 caracteres). */
    public const CHARS = 200;

    /**
     * Lo que sólo aparece al juntar el final de $before con el principio de $after. La costura
     * se revisa entera y se le restan los hallazgos de cada mitad por separado, que ya cuentan
     * en su parte o son restos de una palabra que la ventana cortó en su borde. Null si no queda nada.
     *
     * @param callable(string):ChatLineResult $review
     */
    public static function review(string $before, string $joiner, string $after, callable $review): ?ChatLineResult
    {
        $tail = mb_substr($before, -self::CHARS);
        $head = mb_substr($after, 0, self::CHARS);
        $whole = $review($tail . $joiner . $head);
        if ($whole->getMatches() === []) {
            return null; // lo normal: sin nada que restar, no hace falta revisar las mitades
        }

        $known = [];
        foreach ([...$review($tail)->getMatches(), ...$review($head)->getMatches()] as $match) {
            $key = self::key($match);
            $known[$key] = ($known[$key] ?? 0) + 1;
        }
        $new = [];
        foreach ($whole->getMatches() as $match) {
            $key = self::key($match);
            if (($known[$key] ?? 0) > 0) {
                $known[$key]--;
                continue;
            }
            $new[] = $match;
        }

        return $new === [] ? null : new ChatLineResult($whole->getLine(), $new, $whole->getDecision());
    }

    /** @param array<string,mixed> $match */
    private static function key(array $match): string
    {
        return mb_strtolower((string) ($match['found'] ?? '')) . '|' . ($match['riskType'] ?? '') . '|' . ($match['contentType'] ?? '');
    }
}
