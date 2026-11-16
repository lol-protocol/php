<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Formas regulares del alemán. Adjetivo: las cinco terminaciones («dumm» →
 * «dumme», «dummer», «dummen», «dummes», «dummem»). Sustantivo: las formas que siguen una regla
 * segura según la terminación —plural («Hure» → «Huren», «Idiotin» → «Idiotinnen», «Idiot» → «Idioten»,
 * «Wichser» → «Wichsern», «Beziehung» → «Beziehungen») y genitivo («Wagens», «Wichsers», «Wichtes»)—;
 * los que cambian la vocal («Arsch» → «Ärsche») van a mano en `also`. Las clases salen de medir contra
 * UniMorph con bin/inflection-check.php. Los verbos quedan literales.
 */
final class GermanInflection implements TopicInflection
{
    /** Sustantivos débiles: el plural (y todo caso salvo el nominativo) en -en. */
    private const WEAK = '/(ist|ant|ent|ot|at|ath|graph|nom|soph)$/u';
    /** Por terminación: las formas añadidas al lema (la primera regla que encaja). */
    private const BY_ENDING = [
        '/e$/u' => ['n'],
        '/[^aeiouäöü]in$/u' => ['nen'], // «Äffin»; «Schwein» no
        '/(ng|it|on)$/u' => ['en'], // «Beziehung», «Einheit», «Animation»
        '/en$/u' => ['s'],
        '/(er|el)$/u' => ['n', 's'],
        '/sch$/u' => ['e', 'es'], // «Arsch» → «Arsches» (no «Arschs»)
        '/[dmhkfbp]$/u' => ['s', 'e', 'es'], // «Hund» → «Hunds», «Hunde», «Hundes»
        '/[zs]$/u' => ['e', 'es'],
        '/g$/u' => ['en'],
        '/t$/u' => ['en', 's', 'e', 'es'],
        '/l$/u' => ['s'],
        '/[ao]$/u' => ['s'], // «Auto» → «Autos»
    ];

    public function forms(string $lemma, string $kind): array
    {
        return match ($kind) {
            'adj' => $this->adjective($lemma),
            'noun' => array_values(array_unique([$lemma, ...$this->plural($lemma)])),
            default => [$lemma],
        };
    }

    /** @return array<int,string> */
    private function adjective(string $word): array
    {
        $stem = str_ends_with($word, 'e') ? substr($word, 0, -1) : $word;

        return array_values(array_unique([$word, ...array_map(fn(string $ending): string => $stem . $ending, ['e', 'er', 'en', 'es', 'em'])]));
    }

    /** @return array<int,string> */
    private function plural(string $word): array
    {
        if (preg_match(self::WEAK, $word) === 1) {
            return [$word . 'en'];
        }
        foreach (self::BY_ENDING as $pattern => $endings) {
            if (preg_match($pattern, $word) === 1) {
                return array_map(static fn(string $ending): string => $word . $ending, $endings);
            }
        }

        return [];
    }
}
