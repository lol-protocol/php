<?php

namespace DefamatoryContentReview\Chat;

/**
 * La misma línea revisada en varios idiomas: se suman los hallazgos y manda la
 * decisión más grave. Así, elegir otro idioma no sirve para colar un insulto
 * («Fulano es un estafador» enviado como inglés). Lo usa ModerationEndpoint.
 */
final class CombinedReview
{
    private const ORDER = ['approve' => 0, 'review' => 1, 'reject' => 2];

    /** @param list<ChatLineResult> $results */
    public static function of(string $line, array $results): ChatLineResult
    {
        $matches = [];
        $decision = 'approve';
        foreach ($results as $result) {
            $matches = ChatMatches::merge($matches, $result->getMatches());
            if (self::ORDER[$result->getDecision()] > self::ORDER[$decision]) {
                $decision = $result->getDecision();
            }
        }

        return new ChatLineResult($line, $matches, $decision);
    }
}
