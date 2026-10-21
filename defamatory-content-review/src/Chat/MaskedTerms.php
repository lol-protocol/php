<?php

namespace DefamatoryContentReview\Chat;

use DefamatoryContentReview\Dictionary\WordList;

/**
 * Insultos con letras tapadas por asteriscos: «p*ta», «m*erda», «c**rón».
 * Cada «*» vale por una letra; si la palabra así completada es un término del
 * diccionario, se marca. Nunca bloquea sola (la letra tapada podría dar otra
 * palabra): a lo sumo va a revisión, con `masked => true`. Colaborador
 * interno de ChatLineReviewer.
 */
final class MaskedTerms
{
    private const TOKEN = '/(?<![\p{L}\p{N}*])[\p{L}\p{N}]*\*+[\p{L}\p{N}*]*(?![\p{L}\p{N}*])/u';
    private const PLACEHOLDER = "\u{1}";

    /** @return array<int,array<string,mixed>> */
    public static function find(string $line, WordList $dictionary): array
    {
        if (!str_contains($line, '*') || preg_match_all(self::TOKEN, $line, $tokens) === 0) {
            return [];
        }
        $matches = [];
        foreach ($tokens[0] as $token) {
            $stars = substr_count($token, '*');
            $length = mb_strlen($token);
            if ($length < 3 || $length > 20 || $stars * 2 > $length) {
                continue; // «***» o «a*» no dicen nada
            }
            $entry = self::bestMatch($dictionary, $dictionary->normalize(str_replace('*', self::PLACEHOLDER, $token)));
            if ($entry !== null) {
                $matches[] = [
                    'severity' => $entry['severity'] === 'high' ? 'medium' : $entry['severity'],
                    'contentType' => $entry['riskType'] === 'burlesco' ? 'burlesco' : 'difamatorio',
                    'found' => $token,
                    'masked' => true,
                ] + $entry;
            }
        }

        return $matches;
    }

    /** @return array<string,mixed>|null el término más grave que encaja, sin los `ambiguous` */
    private static function bestMatch(WordList $dictionary, string $masked): ?array
    {
        $pattern = '/^' . str_replace(preg_quote(self::PLACEHOLDER, '/'), '\p{L}', preg_quote($masked, '/')) . '$/u';
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3];
        $best = null;
        foreach ($dictionary->getAllWords() as $key => $entry) {
            if (!$entry['ambiguous'] && mb_strlen((string) $key) === mb_strlen($masked) && preg_match($pattern, (string) $key) === 1
                && ($best === null || $rank[$entry['severity']] > $rank[$best['severity']])) {
                $best = $entry;
            }
        }

        return $best;
    }
}
