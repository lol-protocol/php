<?php

namespace DefamatoryContentReview\Chat;

/** Operaciones sobre la lista de hallazgos de una línea de chat. Colaborador interno de ChatLineReviewer. */
final class ChatMatches
{
    private const DECISION_BY_SEVERITY = ['high' => 'reject', 'medium' => 'review', 'low' => 'approve'];

    /** La decisión la fija el término más grave: high bloquea, medium va a revisión, low sólo se informa.
     * @param array<int,array<string,mixed>> $matches */
    public static function decision(array $matches): string
    {
        $severities = array_column($matches, 'severity');
        foreach (self::DECISION_BY_SEVERITY as $severity => $decision) {
            if (in_array($severity, $severities, true)) {
                return $decision;
            }
        }

        return 'approve';
    }

    /**
     * Devuelve a `found` el texto original de las rachas de letras sueltas
     * que ChatLineNormalizer unió: «puta» pasa a ser «p u t a».
     *
     * @param array<int,array<string,mixed>> $matches
     * @param array<string,string> $joined `unida => original` de ChatLineNormalizer::variants()
     * @return array<int,array<string,mixed>>
     */
    public static function restore(array $matches, array $joined): array
    {
        if ($joined === []) {
            return $matches;
        }

        return array_map(fn(array $match): array => ['found' => strtr($match['found'], $joined)] + $match, $matches);
    }

    /**
     * Suma a `$known` lo que `$other` vio de más: dos lecturas de la misma línea
     * encuentran casi siempre lo mismo, y eso no debe contarse dos veces. Las
     * repeticiones reales («puta puta») sí.
     *
     * @param array<int,array<string,mixed>> $known
     * @param array<int,array<string,mixed>> $other
     * @return array<int,array<string,mixed>>
     */
    public static function merge(array $known, array $other): array
    {
        $left = [];
        foreach ($known as $match) {
            $left[self::key($match)] = ($left[self::key($match)] ?? 0) + 1;
        }
        foreach ($other as $match) {
            if (($left[self::key($match)] = ($left[self::key($match)] ?? 0) - 1) < 0) {
                $known[] = $match;
            }
        }

        return $known;
    }

    /**
     * Lo que sólo se encontró reduciendo letras dobles («puuta» → «puta») nunca
     * bloquea solo: puede ser un apellido («Pratt» → «prat»). Lo que era `high`
     * pasa a `medium`, o sea a revisión.
     *
     * @param array<int,array<string,mixed>> $matches
     * @return array<int,array<string,mixed>>
     */
    public static function downgradeDoubled(array $matches): array
    {
        return array_map(
            fn(array $match): array => ($match['repeat'] ?? '') === 'doubled' && $match['severity'] === 'high' ? ['severity' => 'medium'] + $match : $match,
            $matches
        );
    }

    /** @param array<string,mixed> $match */
    private static function key(array $match): string
    {
        return $match['found'] . '|' . $match['riskType'] . '|' . $match['contentType'];
    }
}
