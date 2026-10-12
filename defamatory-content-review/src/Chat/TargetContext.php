<?php

namespace DefamatoryContentReview\Chat;

/**
 * Insultos que sólo cuentan dirigidos a alguien: «eres un cerdo» sí, «comí
 * cerdo» no. Aplica a los términos de los riskType de `targeted.types`
 * (animales, discapacidad) y a las entradas `'targeted' => true` de un
 * diccionario (identidad, brujería, palabrotas suaves). Colaborador interno
 * de ChatLineReviewer.
 *
 * Cuenta como dirigido si, cerca del término, hay una marca del idioma:
 * `before` hasta 3 palabras antes («eres», «seu», «espèce de», «ты»),
 * `after` hasta 3 después («de mierda», «senkin»… «ka» en tagalo). Si la
 * línea es sólo el término («¡cerdo!») cuenta como siempre. Las frases de
 * varias palabras («hijo de perra») no dependen del contexto, y una entrada
 * `'targeted' => false` («svinehund») tampoco.
 * Si la misma línea ya trae un insulto firme (medium o high, no contextual),
 * el contexto ya es de insulto y el término cuenta como siempre. Si no,
 * baja a `low`: se informa con `context => untargeted`, pero no se censura.
 */
final class TargetContext
{
    private const CONTENT_TYPES = ['difamatorio', 'burlesco'];

    /**
     * @param array<int,array<string,mixed>> $matches
     * @param array{types?:list<string>, before?:list<string>, after?:list<string>} $rules `targeted` de config/chat-topics
     * @return array<int,array<string,mixed>>
     */
    public static function apply(string $line, array $matches, array $rules, string $language): array
    {
        if ($rules === []) {
            return $matches;
        }
        $types = array_flip($rules['types'] ?? []);
        $contextual = fn(array $m): bool => in_array($m['contentType'] ?? '', self::CONTENT_TYPES, true)
            && ($m['targeted'] ?? (isset($types[$m['riskType'] ?? '']) && !str_contains((string) ($m['original'] ?? ''), ' ')));
        foreach ($matches as $match) {
            if (!$contextual($match) && in_array($match['contentType'] ?? '', self::CONTENT_TYPES, true) && $match['severity'] !== 'low') {
                return $matches; // ya hay un insulto firme en la línea
            }
        }
        $tokens = TargetWindow::tokens($line, $language);
        $before = TargetWindow::phrases($rules['before'] ?? [], $language);
        $after = TargetWindow::phrases($rules['after'] ?? [], $language);

        return array_map(function (array $match) use ($contextual, $tokens, $before, $after, $language): array {
            if (!$contextual($match)) {
                return $match;
            }
            $found = TargetWindow::tokens((string) $match['found'], $language);
            if ($found === $tokens) {
                return $match; // la línea es sólo el término: «¡cerdo!»
            }
            // sin ubicar (letras sueltas unidas…) se cuenta: mejor eso que perderlo
            return (TargetWindow::marked($tokens, $found, $before, $after) ?? true)
                ? $match
                : ['severity' => 'low', 'context' => 'untargeted'] + $match;
        }, $matches);
    }
}
