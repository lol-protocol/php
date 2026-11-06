<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Qué idiomas tienen reglas de flexión (TopicInflection) y cómo se etiquetan sus formas, en una sola tabla
 * `idioma => [clase, etiqueta]`. La etiqueta es la de toda forma distinta del lema: `null` si se distingue
 * singular y plural (`sg`/`pl`, géneros del adjetivo); `infl` en los que declinan por casos (ruso, polaco…:
 * no se sabe si es plural); `adj:infl` si solo el adjetivo se declina (alemán, nórdicos); `lig` en tagalo,
 * donde la única forma nueva es el ligamento «-ng» («tangang»), que no es un caso ni un plural.
 */
final class InflectionLanguages
{
    private const TABLE = [
        'spa' => [SpanishInflection::class, null], 'eng' => [EnglishInflection::class, null], 'por' => [PortugueseInflection::class, null],
        'ita' => [ItalianInflection::class, null], 'fra' => [FrenchInflection::class, null], 'tur' => [TurkishInflection::class, null],
        'deu' => [GermanInflection::class, 'adj:infl'], 'nld' => [DutchInflection::class, 'adj:infl'], 'swe' => [SwedishInflection::class, 'adj:infl'],
        'dan' => [DanishInflection::class, 'adj:infl'], 'nor' => [NorwegianInflection::class, 'adj:infl'],
        'rus' => [RussianInflection::class, 'infl'], 'ukr' => [UkrainianInflection::class, 'infl'], 'pol' => [PolishInflection::class, 'infl'],
        'ces' => [CzechInflection::class, 'infl'], 'slk' => [SlovakInflection::class, 'infl'], 'bul' => [BulgarianInflection::class, 'infl'],
        'ron' => [RomanianInflection::class, 'infl'], 'hun' => [HungarianInflection::class, 'infl'], 'ell' => [GreekInflection::class, 'infl'],
        'heb' => [HebrewInflection::class, 'infl'], 'isl' => [IcelandicInflection::class, 'infl'], 'fin' => [FinnishInflection::class, 'infl'],
        'ara' => [ArabicInflection::class, 'infl'], 'hin' => [HindiInflection::class, 'infl'], 'tgl' => [TagalogInflection::class, 'lig'],
    ];

    /** @return class-string<TopicInflection>|null */
    public static function classFor(string $language): ?string
    {
        return self::TABLE[$language][0] ?? null;
    }

    /** La etiqueta de las formas distintas del lema de este tipo, o null si se etiquetan `sg`/`pl` según su número. */
    public static function tagFor(string $language, string $kind): ?string
    {
        $tag = self::TABLE[$language][1] ?? null;
        if ($kind === 'verb' || $tag === null) {
            return null;
        }

        return $tag === 'adj:infl' ? ($kind === 'adj' ? 'infl' : null) : $tag;
    }
}
