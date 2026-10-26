<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Qué idiomas tienen reglas de flexión (TopicInflection) y cómo se etiquetan sus formas:
 * en los que declinan por casos (ruso, polaco…) toda forma distinta del lema es `infl`
 * (no se sabe si es plural); en los que solo declinan el adjetivo (alemán, nórdicos), el adjetivo.
 */
final class InflectionLanguages
{
    private const BY_LANGUAGE = [
        'spa' => SpanishInflection::class, 'eng' => EnglishInflection::class, 'por' => PortugueseInflection::class,
        'ita' => ItalianInflection::class, 'fra' => FrenchInflection::class, 'deu' => GermanInflection::class,
        'nld' => DutchInflection::class, 'tur' => TurkishInflection::class, 'swe' => SwedishInflection::class,
        'dan' => DanishInflection::class, 'nor' => NorwegianInflection::class, 'rus' => RussianInflection::class,
        'ukr' => UkrainianInflection::class, 'pol' => PolishInflection::class, 'ces' => CzechInflection::class,
        'slk' => SlovakInflection::class, 'bul' => BulgarianInflection::class, 'ron' => RomanianInflection::class,
        'hun' => HungarianInflection::class, 'ell' => GreekInflection::class, 'heb' => HebrewInflection::class,
    ];
    /** Sustantivo y adjetivo declinan por casos. */
    private const CASES = ['rus', 'ukr', 'pol', 'ces', 'slk', 'bul', 'ron', 'hun', 'ell', 'heb'];
    /** Solo el adjetivo se declina sin cambiar de género. */
    private const ADJECTIVE_ONLY = ['deu', 'nld', 'swe', 'dan', 'nor'];

    /** @return class-string<TopicInflection>|null */
    public static function classFor(string $language): ?string
    {
        return self::BY_LANGUAGE[$language] ?? null;
    }

    /** ¿Las formas de este tipo se etiquetan todas `infl`? */
    public static function inflects(string $language, string $kind): bool
    {
        return ($kind === 'adj' && in_array($language, self::ADJECTIVE_ONLY, true)) || (in_array($language, self::CASES, true) && $kind !== 'verb');
    }
}
