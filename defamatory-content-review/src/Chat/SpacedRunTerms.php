<?php

namespace DefamatoryContentReview\Chat;

use DefamatoryContentReview\Dictionary\WordList;

/**
 * Términos dentro de una racha de letras sueltas que junta varias palabras:
 * «h o l a p u t a», «p u t a p u t a». SpacedLetters une la racha entera y
 * el diccionario la busca como una sola palabra («holaputa»), así que esto no
 * lo veía. Nadie escribe letra por letra sin querer esquivar el filtro, de modo
 * que buscar dentro de la racha es razonable; pero una palabra deletreada
 * puede contener un insulto («c o m p u t a d o r a»), así que lo encontrado
 * aquí nunca bloquea solo: como mucho va a revisión (`spaced => inside`).
 *
 * Sólo términos de MIN_LETTERS letras o más, ni `low` ni ambiguos, de más
 * largo a más corto y sin solaparse. Fuera de las rachas no cambia nada.
 * Colaborador interno de ChatLineReviewer.
 */
final class SpacedRunTerms
{
    private const MIN_LETTERS = 4;
    private const MAX_LETTERS = 24;

    /**
     * @param array<int,array<string,mixed>> $known lo ya encontrado en la línea: no se cuenta dos veces
     * @return array<int,array<string,mixed>>
     */
    public static function find(string $line, WordList $dictionary, ?ChatTopics $topics, array $known): array
    {
        $seen = array_flip(array_map(fn(array $match): string => (string) $match['original'], $known));
        $found = [];
        foreach (SpacedLetters::runs($line) as $letters) {
            $count = count($letters);
            for ($i = 0; $i < $count; $i++) {
                for ($length = min(self::MAX_LETTERS, $count - $i); $length >= self::MIN_LETTERS; $length--) {
                    $piece = array_slice($letters, $i, $length);
                    $hits = $length === $count ? [] : self::lookup(implode('', $piece), $dictionary, $topics);
                    if ($hits === []) {
                        continue;
                    }
                    foreach ($hits as $hit) {
                        if (!isset($seen[$hit['original']])) {
                            $found[] = ['found' => implode(' ', $piece), 'spaced' => 'inside'] + $hit;
                        }
                    }
                    $i += $length - 1;
                    break;
                }
            }
        }

        return $found;
    }

    /** @return array<int,array<string,mixed>> el término en el diccionario y en los temas, con su tipo y la severidad tope */
    private static function lookup(string $word, WordList $dictionary, ?ChatTopics $topics): array
    {
        $hits = [];
        $entry = $dictionary->search($word);
        if ($entry !== null) {
            $hits[] = $entry + ['contentType' => $entry['riskType'] === 'burlesco' ? 'burlesco' : 'difamatorio'];
        }
        $topic = $topics?->word($word);
        if ($topic !== null && $topic['category'] !== 'ambiguous') {
            $hits[] = $topic + ['contentType' => $topic['riskType']];
        }

        return array_values(array_map(
            fn(array $hit): array => ['severity' => 'medium'] + $hit,
            array_filter($hits, fn(array $hit): bool => !$hit['ambiguous'] && $hit['severity'] !== 'low')
        ));
    }
}
