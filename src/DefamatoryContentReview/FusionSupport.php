<?php

namespace DefamatoryContentReview;

/**
 * Qué idiomas tienen detección de fusión nombre+apellido ("Elba Gina" →
 * "el vagina") y con qué plegado.
 *
 * La fusión en sí no necesita reglas fonéticas: es unir los dos campos y
 * buscar un término que cruce la unión. Los 17 idiomas de
 * PhoneticFolderRegistry la hacen sobre su forma fonética; los de
 * LITERAL_FUSION, sobre la misma forma normalizada que usa la búsqueda
 * literal. Un barrido de ~1.100 combinaciones de nombres reales comunes dio
 * cero falsos positivos en todos ellos. En coreano la longitud mínima se
 * cuenta en sílabas (bloques Hangul), así que sólo alcanza a los términos
 * más largos: cobertura parcial, pero sin riesgo añadido.
 *
 * Quedan fuera, con motivo:
 * - Inglés y árabe: el barrido sí dio falsos positivos con nombres muy
 *   comunes ("Chris Hitt" → "shit", "Dustin King" → "stinking",
 *   "محمد منصور" → "مدمن").
 * - Hebreo: como el árabe, no escribe vocales, así que las uniones forman
 *   palabras con mucha más facilidad.
 * - Japonés, tailandés y cantonés: no separan palabras con espacios; el
 *   umbral de longitud mínima (en caracteres) está pensado para alfabetos.
 * - Vietnamita: el tono distingue palabras, y la normalización colapsa
 *   parte de los tonos (ver PhoneticFolderRegistry).
 */
final class FusionSupport
{
    private const LITERAL_FUSION = ['rus', 'ukr', 'bul', 'ell', 'hin', 'kor', 'isl', 'swa', 'tgl'];
    /** @var array<string,true> cached for O(1) lookup */
    private static ?array $literalFusionMap = null;

    public static function isSupported(string $language): bool
    {
        if (PhoneticFolderRegistry::isSupported($language)) {
            return true;
        }
        self::$literalFusionMap ??= array_flip(self::LITERAL_FUSION);
        return isset(self::$literalFusionMap[$language]);
    }

    /** Forma sobre la que se busca la fusión: fonética si el idioma tiene reglas, literal si no. */
    public static function fold(string $language, string $text): string
    {
        if (PhoneticFolderRegistry::isSupported($language)) {
            return PhoneticFolderRegistry::fold($language, $text);
        }

        $text = AccentFolding::fold(Leetspeak::unleet(mb_strtolower(trim($text), 'UTF-8')));

        return preg_replace('/[\s\-\'’]+/u', '', ScriptFolding::fold($text));
    }

    /** @var array<int,string>|null cached merged list */
    private static ?array $supportedLanguagesCache = null;

    /** @return array<int,string> */
    public static function supportedLanguages(): array
    {
        return self::$supportedLanguagesCache ??= array_merge(PhoneticFolderRegistry::supportedLanguages(), self::LITERAL_FUSION);
    }
}
