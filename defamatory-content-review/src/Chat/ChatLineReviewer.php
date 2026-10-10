<?php

namespace DefamatoryContentReview\Chat;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\Dictionary\WordList;

/**
 * Revisa una línea libre (un mensaje de chat) y dice si hay que censurarla.
 * Cuatro tipos de contenido:
 *
 * - `difamatorio` y `burlesco`: los insultos del diccionario del idioma, los
 *   mismos de validateName(); son burlescos los de riskType `burlesco`;
 * - `sexual` y `belico` (guerra, violencia y amenazas): listas propias en
 *   config/chat-topics/<código>.php (ver ChatTopics): no son insultos y no
 *   deben afectar la validación de nombres.
 *
 * Igual que con los nombres, un término que también es apellido
 * (`nameCollision`: «Savage», «Concha») nunca bloquea solo: baja a revisión.
 * Las letras sueltas («p u t a») se unen y las repetidas («puuuta») se leen
 * antes de buscar; dentro de una racha de letras sueltas más larga
 * («h o l a p u t a») se busca aparte (ver SpacedRunTerms). Una entrada `'ambiguous' => true` de un diccionario
 * («яйца», «leche») se ignora aquí: casi siempre es la palabra cotidiana.
 */
final class ChatLineReviewer
{
    private const DECISION_BY_SEVERITY = ['high' => 'reject', 'medium' => 'review', 'low' => 'approve'];

    /** @var array<string,?ChatTopics> idioma => temas (null: el idioma no tiene lista) */
    private array $topics = [];

    public function __construct(private readonly DefamatoryContentReviewer $reviewer, private readonly string $topicsDir) { }

    public static function create(string $configDir, string $language = 'spa'): self
    {
        return new self(DefamatoryContentReviewer::create($configDir, $language), rtrim($configDir, '/') . '/chat-topics');
    }

    public function review(string $line): ChatLineResult
    {
        $topics = $this->topicsFor($this->reviewer->getLanguage());
        $matches = [];

        foreach (SpacedLetters::variants($line) as [$text, $joined]) {
            $matches = ChatMatches::merge($matches, ChatMatches::restore($this->scan($text, $topics), $joined));
        }
        $matches = [...$matches, ...SpacedRunTerms::find($line, $this->dictionary(), $topics, $matches)];

        return new ChatLineResult($line, $matches, $this->decisionFor($matches));
    }

    /**
     * @param string $text una lectura de la línea (con las letras sueltas unidas)
     * @return array<int,array<string,mixed>>
     */
    private function scan(string $text, ?ChatTopics $topics): array
    {
        $dictionary = $this->dictionary();
        $matches = [];
        foreach ($topics?->scan($dictionary, $text) ?? $dictionary->findInText($text) as $match) {
            if ($match['ambiguous'] ?? false) { continue; } // «яйца», «leche»: palabra cotidiana, no insulto en un chat
            $matches[] = [
                'severity' => $match['nameCollision'] && $match['severity'] === 'high' ? 'medium' : $match['severity'],
                'contentType' => $match['riskType'] === 'burlesco' ? 'burlesco' : 'difamatorio',
            ] + $match;
        }
        foreach ($topics?->find($text) ?? [] as $match) {
            $matches[] = $match + ['contentType' => $match['riskType']];
        }

        return ChatMatches::downgradeDoubled($matches);
    }

    /** La decisión la fija el término más grave: high bloquea, medium va a revisión, low sólo se informa.
     * @param array<int,array<string,mixed>> $matches */
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

    private function dictionary(): WordList { return $this->reviewer->languages()->wordList($this->reviewer->getLanguage()); }

    private function topicsFor(string $language): ?ChatTopics
    {
        if (!array_key_exists($language, $this->topics)) {
            $this->topics[$language] = ChatTopics::fromFile(rtrim($this->topicsDir, '/') . '/' . $language . '.php', $language);
        }

        return $this->topics[$language];
    }
}
