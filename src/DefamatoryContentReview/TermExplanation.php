<?php

namespace DefamatoryContentReview;

/** Frase legible de por qué se marcó un término, para el revisor humano de los casos que van a 'review'. */
final class TermExplanation
{
    /** @param array<string,mixed> $term entrada de FlaggedTermCollection */
    public static function of(array $term): string
    {
        $entry = $term['matchedEntry'] ?? $term['term'];
        $where = sprintf('diccionario %s, tipo %s, severidad %s', $term['sourceLanguage'], $term['riskType'], $term['severity']);

        $text = match ($term['detectionMethod']) {
            'phonetic_fusion' => sprintf("«%s» leído seguido suena como «%s» (%s)", $term['fusedFrom'] ?? $term['term'], $entry, $where),
            'phonetic_variant' => sprintf("«%s» suena igual que «%s» con otra grafía (%s)", $term['term'], $entry, $where),
            default => sprintf("«%s» coincide con «%s» (%s)", $term['term'], $entry, $where),
        };

        if (($term['confidence'] ?? 1.0) < 1.0) {
            $text .= sprintf('; idioma emparentado, confianza %.2f', $term['confidence']);
        }

        if (!empty($term['nameCollision'])) {
            $text .= '; también es nombre o apellido real documentado, va a revisión humana';
        }

        return $text . '.';
    }
}
