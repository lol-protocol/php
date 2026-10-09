<?php

namespace DefamatoryContentReview\Chat;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use DefamatoryContentReview\Dictionary\{WordList, WordListScanner};

/**
 * Lista de temas de chat de un idioma (config/chat-topics/<código>.php):
 * palabras con sus formas regulares, y frases con forma. Colaborador
 * interno de ChatLineReviewer.
 *
 * Las palabras de la categoría `ambiguous` («coger», «bomba», «concha», que
 * también es un nombre) sólo cuentan si la misma línea trae, del mismo
 * riskType, algo que no sea ambiguo ni de severidad `low`. Sin eso, «vamos a
 * coger el bus» sería contenido sexual.
 */
final class ChatTopics
{
    private const AMBIGUOUS = 'ambiguous';

    private WordList $words;
    /** @var array<int,array<string,mixed>> */
    private array $patterns;
    /** @var array<string,true> */
    private array $legit = [];
    private bool $collapseRepeats;

    /** @param array<string,mixed> $config ['meta' => …, 'words' => categoría => entradas, 'patterns' => …] */
    public function __construct(array $config, string $language)
    {
        $words = TopicInflector::expand($config['words'] ?? [], $language);
        $this->words = new WordList(['meta' => $config['meta'] ?? [], 'words' => $words], $language);
        $this->collapseRepeats = (bool) ($config['meta']['collapseRepeats'] ?? false);
        foreach ($config['legit'] ?? [] as $word) {
            $this->legit[RepeatedLetters::key($word)] = true;
        }
        $patterns = $config['patterns'] ?? [];
        $this->patterns = $this->collapseRepeats
            ? array_map(fn(array $pattern): array => ['pattern' => RepeatedLetters::tolerant($pattern['pattern'])] + $pattern, $patterns)
            : $patterns;
    }

    public static function fromFile(string $path, string $language): ?self
    {
        return is_file($path) ? new self(require $path, $language) : null;
    }

    /**
     * Los términos de `$list` en la línea. Donde el idioma lo pide (meta
     * `collapseRepeats`) también los escritos con letras repetidas
     * («puuuta»); si sólo hizo falta reducir dobles, el hallazgo trae
     * `repeat => doubled`. Ver RepeatedLetters.
     *
     * @return array<int,array<string,mixed>>
     */
    public function scan(WordList $list, string $text): array
    {
        return $this->collapseRepeats
            ? WordListScanner::scan($text, RepeatedLetters::searcher(fn(string $phrase): ?array => $list->search($phrase), $this->legit))
            : $list->findInText($text);
    }

    /**
     * @param string $text una lectura de la línea (ver SpacedLetters::variants())
     * @return array<int,array<string,mixed>>
     */
    public function find(string $text): array
    {
        $matches = array_merge($this->scan($this->words, $text), ChatPatternMatcher::find($text, $this->patterns));
        $firm = [];
        foreach ($matches as $match) {
            if ($match['category'] !== self::AMBIGUOUS && $match['severity'] !== 'low') {
                $firm[$match['riskType']] = true;
            }
        }

        return array_values(array_filter(
            $matches,
            fn(array $match): bool => $match['category'] !== self::AMBIGUOUS || isset($firm[$match['riskType']])
        ));
    }
}
