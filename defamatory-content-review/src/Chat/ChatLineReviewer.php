<?php

namespace DefamatoryContentReview\Chat;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\Dictionary\WordList;
use DefamatoryContentReview\Normalization\EvasionCleaner;

/**
 * Revisa una línea libre (un mensaje de chat) y dice si hay que censurarla.
 * Cinco tipos de contenido:
 *
 * - `difamatorio` y `burlesco`: los insultos del diccionario del idioma, los
 *   mismos de validateName(); son burlescos los de riskType `burlesco`;
 * - `sexual` y `belico` (guerra, violencia y amenazas): listas propias en
 *   config/chat-topics/<código>.php (ver ChatTopics); `privado`: RUT, correo,
 *   teléfono o dirección de alguien (PersonalData), en cualquier idioma.
 *
 * Un término que también es apellido (`nameCollision`) nunca bloquea solo: baja a revisión.
 * Una segunda pasada con la línea entera limpia (EvasionCleaner) ve «pu&#116;a» y el texto
 * invertido. Las letras sueltas («p u t a») se unen y las repetidas («puuuta») se leen antes de buscar; las rachas largas («h o l a p u t a») van aparte (SpacedRunTerms), y
 * «p*ta» también (MaskedTerms). Una entrada `'ambiguous' => true` («яйца», «leche») se
 * ignora aquí. Animales, discapacidad y `targeted` sólo cuentan dirigidos a alguien (TargetContext).
 */
final class ChatLineReviewer
{
    /** @var array<string,?ChatTopics> idioma => temas (null: el idioma no tiene lista) */
    private array $topics = [];

    public function __construct(private readonly DefamatoryContentReviewer $reviewer, private readonly string $topicsDir) { }

    public static function create(string $configDir, string $language = 'spa'): self
    {
        return new self(DefamatoryContentReviewer::create($configDir, $language), rtrim($configDir, '/') . '/chat-topics');
    }

    public function review(string $line): ChatLineResult
    {
        $line = mb_scrub($line, 'UTF-8'); // un byte inválido hacía fallar todas las expresiones regulares: se aprobaba
        $topics = $this->topicsFor($language = $this->reviewer->getLanguage());
        $targeting = $topics?->targeting() ?? [];
        $matches = TargetContext::apply($line, $this->find($line, $topics), $targeting, $language);
        if (($clean = EvasionCleaner::clean($line, $language)) !== $line) { // lo que sólo se ve con la línea entera limpia
            $seen = array_map(fn(array $m): string => $m['original'] ?? $m['found'], $matches);
            $extra = array_filter($this->find($clean, $topics), fn(array $m): bool => !in_array($m['original'] ?? $m['found'], $seen, true));
            $matches = [...$matches, ...TargetContext::apply($clean, array_values($extra), $targeting, $language)];
        }
        $matches = [...$matches, ...PersonalData::find($line)];

        return new ChatLineResult($line, $matches, ChatMatches::decision($matches));
    }

    /** @return array<int,array<string,mixed>> */
    private function find(string $line, ?ChatTopics $topics): array
    {
        $matches = [];
        foreach (SpacedLetters::variants($line) as [$text, $joined]) {
            $matches = ChatMatches::merge($matches, ChatMatches::restore($this->scan($text, $topics), $joined));
        }

        return [...$matches, ...SpacedRunTerms::find($line, $this->dictionary(), $topics, $matches), ...MaskedTerms::find($line, $this->dictionary())];
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

    private function dictionary(): WordList { return $this->reviewer->languages()->wordList($this->reviewer->getLanguage()); }

    private function topicsFor(string $language): ?ChatTopics
    {
        if (!array_key_exists($language, $this->topics)) {
            $this->topics[$language] = ChatTopics::fromFile(rtrim($this->topicsDir, '/') . '/' . $language . '.php', $language);
        }

        return $this->topics[$language];
    }
}
