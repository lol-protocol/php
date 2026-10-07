<?php

namespace DefamatoryContentReview;

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

    /** @param array<string,mixed> $config ['meta' => …, 'words' => categoría => entradas, 'patterns' => …] */
    public function __construct(array $config, string $language)
    {
        $words = TopicInflector::expand($config['words'] ?? [], $language);
        $this->words = new WordList(['meta' => $config['meta'] ?? [], 'words' => $words], $language);
        $this->patterns = $config['patterns'] ?? [];
    }

    public static function fromFile(string $path, string $language): ?self
    {
        return is_file($path) ? new self(require $path, $language) : null;
    }

    /**
     * @param string $text la línea con las letras sueltas ya unidas: lo que lee el diccionario
     * @param string $line la línea original: los patrones se posicionan sobre ella
     * @return array<int,array<string,mixed>>
     */
    public function find(string $text, string $line): array
    {
        $matches = array_merge($this->words->findInText($text), ChatPatternMatcher::find($line, $this->patterns));
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
