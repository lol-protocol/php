<?php

namespace DefamatoryContentReview;

/**
 * Revisa una línea libre (un mensaje de chat) y dice si hay que censurarla.
 * Cuatro tipos de contenido:
 *
 * - `difamatorio`: cualquier insulto del diccionario del idioma (los mismos
 *   que usa validateName()), salvo los burlescos;
 * - `burlesco`: los términos de riskType `burlesco` de ese diccionario;
 * - `sexual` y `belico` (guerra, violencia y amenazas): listas propias en
 *   config/chat-topics/<código>.php — ver ChatTopics —, porque no son
 *   insultos y no deben afectar la validación de nombres.
 *
 * Igual que con los nombres, un término que también es apellido
 * (`nameCollision`: «Savage», «Concha») nunca bloquea solo: baja a revisión.
 * Las letras sueltas («p u t a») se unen antes de buscar.
 */
final class ChatLineReviewer
{
    private const DECISION_BY_SEVERITY = ['high' => 'reject', 'medium' => 'review', 'low' => 'approve'];

    /** @var array<string,?ChatTopics> idioma => temas (null: el idioma no tiene lista) */
    private array $topics = [];

    public function __construct(
        private readonly DefamatoryContentReviewer $reviewer,
        private readonly string $topicsDir
    ) { }

    public static function create(string $configDir, string $language = 'spa'): self
    {
        $configDir = rtrim($configDir, '/');

        return new self(DefamatoryContentReviewer::create($configDir, $language), $configDir . '/chat-topics');
    }

    public function review(string $line): ChatLineResult
    {
        $matches = [];
        foreach (ChatLineNormalizer::variants($line) as [$text, $joined]) {
            $matches = ChatMatches::merge($matches, ChatMatches::restore($this->scan($text, $line), $joined));
        }

        return new ChatLineResult($line, $matches, $this->decisionFor($matches));
    }

    /**
     * @param string $text la línea con las letras sueltas unidas
     * @param string $line la línea original
     * @return array<int,array<string,mixed>>
     */
    private function scan(string $text, string $line): array
    {
        $language = $this->reviewer->getLanguage();
        $matches = [];

        foreach ($this->reviewer->languages()->wordList($language)->findInText($text) as $match) {
            $matches[] = [
                'severity' => $match['nameCollision'] && $match['severity'] === 'high' ? 'medium' : $match['severity'],
                'contentType' => $match['riskType'] === 'burlesco' ? 'burlesco' : 'difamatorio',
            ] + $match;
        }
        foreach ($this->topicsFor($language)?->find($text, $line) ?? [] as $match) {
            $matches[] = $match + ['contentType' => $match['riskType']];
        }

        return $matches;
    }

    /**
     * La decisión la fija el término más grave: high bloquea, medium va a revisión, low sólo se informa.
     *
     * @param array<int,array<string,mixed>> $matches
     */
    private function decisionFor(array $matches): string
    {
        $severities = array_column($matches, 'severity');
        foreach (self::DECISION_BY_SEVERITY as $severity => $decision) {
            if (in_array($severity, $severities, true)) {
                return $decision;
            }
        }

        return 'approve';
    }

    private function topicsFor(string $language): ?ChatTopics
    {
        if (!array_key_exists($language, $this->topics)) {
            $this->topics[$language] = ChatTopics::fromFile(rtrim($this->topicsDir, '/') . '/' . $language . '.php', $language);
        }

        return $this->topics[$language];
    }
}
