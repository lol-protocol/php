<?php

namespace DefamatoryContentReview\Chat;

use DefamatoryContentReview\Dictionary\WordList;

/**
 * Términos dentro de una racha de letras sueltas que junta varias palabras
 * («h o l a p u t a»): SpacedLetters la une entera («holaputa») y el
 * diccionario no la ve. Nadie escribe letra por letra sin querer esquivar el
 * filtro, así que se busca adentro; pero una palabra deletreada puede contener
 * un insulto («c o m p u t a d o r a»): lo de aquí a lo sumo va a revisión
 * (`spaced => inside`). Sólo términos de MIN_LETTERS letras o más, ni `low` ni
 * ambiguos, de más largo a más corto y sin solaparse. Colaborador de ChatLineReviewer.
 */
final class SpacedRunTerms
{
    private const MIN_LETTERS = 4;
    private const MAX_LETTERS = 24;
    /**
     * Letras sueltas que se revisan por línea, sumando todas sus rachas. Nadie deletrea tanto sin querer
     * esconder algo: lo que pasa del tope va entero a revisión sin buscar adentro, que costaría segundos.
     */
    private const MAX_SPELLED = 120;

    /**
     * @param array<int,array<string,mixed>> $known lo ya encontrado en la línea: no se cuenta dos veces
     * @return array<int,array<string,mixed>>
     */
    public static function find(string $line, WordList $dictionary, ?ChatTopics $topics, array $known): array
    {
        $seen = array_flip(array_map(fn(array $match): string => (string) $match['original'], $known));
        $found = [];
        $budget = self::MAX_SPELLED;
        foreach (SpacedLetters::runs($line) as $letters) {
            $count = count($letters);
            if (($budget -= $count) < 0) {
                $found[] = ['found' => implode(' ', $letters), 'original' => 'texto deletreado', 'category' => 'patterns', 'riskType' => 'difamatorio',
                    'contentType' => 'difamatorio', 'severity' => 'medium', 'nameCollision' => false, 'ambiguous' => false, 'spaced' => 'long'];
                continue;
            }
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
            $found = [...$found, ...self::spelledPhrases($letters, $topics)];
        }

        return $found;
    }

    /**
     * Una frase con forma deletreada entera: «t e v o y a m a t a r». Los patrones
     * se prueban sobre la racha unida, con los espacios opcionales y sin `\b` (en «iwillkillyou»
     * no hay fronteras de palabra). Nadie deletrea una amenaza sin querer: esto sí puede bloquear.
     *
     * @param list<string> $letters
     * @return array<int,array<string,mixed>>
     */
    private static function spelledPhrases(array $letters, ?ChatTopics $topics): array
    {
        $patterns = array_map(fn(array $p): array => ['pattern' => preg_replace(['/\\\\s[+*]?/', '/\\\\b/'], ['\\s*', ''], $p['pattern'])] + $p, $topics?->patterns() ?? []);
        $found = ChatPatternMatcher::find(implode('', $letters), $patterns);

        return array_map(fn(array $match): array => ['found' => implode(' ', $letters), 'contentType' => $match['riskType'], 'spaced' => 'phrase'] + $match, $found);
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
