<?php

namespace DefamatoryContentReview;

/**
 * Revisa una línea libre (un mensaje de chat) y dice si hay que censurarla.
 * Cuatro tipos de contenido:
 *
 * - `difamatorio`: cualquier insulto del diccionario del idioma (los mismos
 *   que usa validateName()), salvo los burlescos;
 * - `burlesco`: los términos de riskType `burlesco` de ese diccionario;
 * - `sexual` y `belico`: listas propias en config/chat-topics/<código>.php,
 *   porque no son insultos y no deben afectar la validación de nombres.
 *
 * A diferencia de los nombres, en un chat no hay apellidos legítimos que
 * proteger: `nameCollision` no baja la decisión a revisión.
 */
final class ChatLineReviewer
{
    private const DECISION_BY_SEVERITY = ['high' => 'reject', 'medium' => 'review', 'low' => 'approve'];

    /** @var array<string,?WordList> idioma => lista de temas (null: el idioma no tiene) */
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
        $language = $this->reviewer->getLanguage();
        $matches = [];

        foreach ($this->reviewer->languages()->wordList($language)->findInText($line) as $match) {
            $matches[] = $match + ['contentType' => $match['riskType'] === 'burlesco' ? 'burlesco' : 'difamatorio'];
        }
        foreach ($this->topicList($language)?->findInText($line) ?? [] as $match) {
            $matches[] = $match + ['contentType' => $match['riskType']];
        }

        return new ChatLineResult($line, $matches, $this->decisionFor($matches));
    }

    /** La decisión la fija el término más grave: high bloquea, medium va a revisión, low sólo se informa. @param array<int,array<string,mixed>> $matches */
    /** @param array<int,array<string,mixed>> $matches */
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

    private function topicList(string $language): ?WordList
    {
        if (!array_key_exists($language, $this->topics)) {
            $path = rtrim($this->topicsDir, '/') . '/' . $language . '.php';
            $this->topics[$language] = is_file($path) ? WordList::fromLanguageFile($path, $language) : null;
        }

        return $this->topics[$language];
    }
}
