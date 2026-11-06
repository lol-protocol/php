<?php

namespace DefamatoryContentReview\Report;

/** Frase legible de por qué se marcó un término, para el revisor humano de los casos que van a 'review'. */
final class TermExplanation
{
    /**
     * @param array<string,mixed> $term entrada de FlaggedTermCollection
     * @param string $locale idioma de la frase (ISO 639-3, ver `Messages::locales()`); si no hay mensajes en él, español
     * @param string|null $dir carpeta de mensajes (por omisión la del paquete)
     */
    public static function of(array $term, string $locale = Messages::DEFAULT_LOCALE, ?string $dir = null): string
    {
        $entry = $term['matchedEntry'] ?? $term['term'];
        $where = Messages::get('explanation.where', $locale, [$term['sourceLanguage'], $term['riskType'], $term['severity']], $dir);

        [$key, $written] = match ($term['detectionMethod']) {
            'phonetic_fusion' => ['fusion', $term['fusedFrom'] ?? $term['term']],
            'phonetic_variant' => ['variant', $term['term']],
            default => ['match', $term['term']],
        };
        $text = Messages::get('explanation.' . $key, $locale, [$written, $entry, $where], $dir);

        if (($term['confidence'] ?? 1.0) < 1.0) {
            $text .= Messages::get('explanation.related', $locale, [$term['confidence']], $dir);
        }

        if (!empty($term['nameCollision'])) {
            $text .= Messages::get('explanation.collision', $locale, [], $dir);
        }

        return $text . Messages::get('explanation.end', $locale, [], $dir);
    }
}
