<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Diminutivos regulares, con sus rasgos (`dim.sg`, `dim.f.pl`…). Cambian el tono
 * en cualquier dirección: «putita» degrada más que «puta»; «cerdita» suele ser
 * cariñoso. Por eso sólo se generan si la entrada lo pide con una clave `dim`
 * en `by` (ver FormSeverity). Español (-ito/-ita, -cito) y portugués
 * (-inho/-inha, -zinho); en italiano y francés el diminutivo no es regular
 * («troietta», «puttanella»): va a mano en `also`.
 */
final class Diminutives
{
    /** @return array<string,string> forma => rasgos; vacío si el idioma o la palabra no tienen regla */
    public static function tagged(string $language, string $lemma, string $kind): array
    {
        $suffixes = match ($language) {
            'spa' => ['o' => 'ito', 'a' => 'ita', 'consonant' => ['cito', 'cita']],
            'por' => ['o' => 'inho', 'a' => 'inha', 'consonant' => ['zinho', 'zinha']],
            default => null,
        };
        if ($suffixes === null || $kind === 'verb') {
            return [];
        }
        $last = mb_substr($lemma, -1);
        $nasal = $language === 'por' && preg_match('/(ão|ao)$/u', $lemma); // ladrão → ladrãozinho (las listas escriben «invasao»)
        if ($nasal || ($last !== 'a' && $last !== 'o')) {
            // sin vocal final no se sabe el género («mujer» → «mujercita», «ladrón» → «ladroncito»): las dos
            return $nasal || preg_match('/[enr]$/u', $lemma) ? self::numbers(array_fill_keys(array_map(fn(string $x): string => $lemma . $x, $suffixes['consonant']), 'dim')) : [];
        }
        $stem = (string) preg_replace(['/c$/', '/g$/'], ['qu', 'gu'], mb_substr($lemma, 0, -1));
        $forms = $kind === 'adj'
            ? [$stem . $suffixes['o'] => 'dim.m', $stem . $suffixes['a'] => 'dim.f']
            : [$stem . $suffixes[$last] => 'dim'];

        return self::numbers($forms);
    }

    /**
     * @param array<string,string> $singulars forma => rasgos sin número
     * @return array<string,string>
     */
    private static function numbers(array $singulars): array
    {
        $tagged = [];
        foreach ($singulars as $form => $tag) {
            $tagged[$form] = "{$tag}.sg";
            $tagged[$form . 's'] = "{$tag}.pl";
        }

        return $tagged;
    }
}
