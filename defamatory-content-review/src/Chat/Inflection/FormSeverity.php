<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * La severidad de una forma generada según sus rasgos y el `by` de su entrada:
 * `'by' => ['f' => 'high', 'pl' => 'none']`. Gana la clave más específica
 * (`f.pl`, después `f`, después `pl`); sin ninguna, la de la entrada. `none`
 * significa «esta forma no es ofensiva»: no se genera. Colaborador de
 * TopicInflector.
 */
final class FormSeverity
{
    private const ORDER = ['low' => 1, 'medium' => 2, 'high' => 3];

    /**
     * @param string $tag rasgos de la forma: `m.sg`, `f.pl`, `sg`, `pl`, `verb`, `dim.f.sg`… o '' (la palabra escrita)
     * @param array<string,string> $by
     * @param bool $cap una forma generada que nadie revisó (sin clave en `by`) llega como mucho a `medium`
     */
    public static function of(string $tag, array $by, string $base, bool $cap = false): string
    {
        if (str_starts_with($tag, 'dim')) { // un diminutivo sólo existe si `by` lo pide: «putita» sí, «cerdita» no
            $parts = explode('.', $tag);
            foreach (array_unique([$tag, 'dim.' . ($parts[1] ?? ''), 'dim.' . end($parts), 'dim']) as $key) {
                if (isset($by[$key])) {
                    return $by[$key];
                }
            }
            return 'none';
        }
        [$gender, $number] = str_contains($tag, '.') ? explode('.', $tag) : ['', $tag];
        foreach (array_filter([$tag, $gender, $number]) as $key) {
            if (isset($by[$key])) {
                return $by[$key];
            }
        }

        return $cap && (self::ORDER[$base] ?? 0) > self::ORDER['medium'] ? 'medium' : $base;
    }
}
