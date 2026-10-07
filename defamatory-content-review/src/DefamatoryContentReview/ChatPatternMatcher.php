<?php

namespace DefamatoryContentReview;

/**
 * Frases con forma («te voy a matar», «ojalá te mueras») que una lista de
 * palabras no puede expresar: tienen más de tres palabras o dependen de qué
 * las rodea. Cada patrón es una expresión regular, sin delimitadores,
 * escrita contra el texto plegado de ChatLineNormalizer::foldForPatterns():
 * minúsculas, sin tildes y con espacios en lugar de puntuación.
 */
final class ChatPatternMatcher
{
    /**
     * @param array<int,array<string,mixed>> $patterns cada uno con pattern, riskType, severity y, opcional, label
     * @return array<int,array<string,mixed>> mismo formato que WordList::findInText()
     */
    public static function find(string $line, array $patterns): array
    {
        $folded = ChatLineNormalizer::foldForPatterns($line);
        $aligned = strlen($folded) === mb_strlen($line);
        $found = [];

        foreach ($patterns as $pattern) {
            if (!preg_match_all('~' . $pattern['pattern'] . '~u', $folded, $hits, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($hits[0] as [$text, $offset]) {
                $found[] = [
                    'original' => $pattern['label'] ?? $pattern['pattern'],
                    'category' => 'patterns',
                    'riskType' => $pattern['riskType'],
                    'severity' => $pattern['severity'],
                    'nameCollision' => false,
                    'found' => $aligned ? mb_substr($line, $offset, strlen($text)) : $text,
                ];
            }
        }

        return $found;
    }
}
