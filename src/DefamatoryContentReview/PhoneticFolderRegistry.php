<?php

namespace DefamatoryContentReview;

/**
 * Qué idiomas tienen reglas de plegado fonético y cuáles son.
 *
 * Cada idioma tiene su propia fonética: lo que confunde a un hispanohablante
 * (b/v, s/z) no es lo mismo que confunde a un lusohablante o a un
 * italohablante. Por eso cada uno tiene su propia clase de plegado en vez de
 * compartir las reglas del español — este registro es sólo el mapa código
 * de idioma → clase, para que WordList y PhoneticFusionDetector no necesiten
 * saber cuál usar.
 *
 * Cubre 17 de los 30 idiomas soportados. Quedan fuera a propósito:
 * - **Inglés**: de script latino, pero —a diferencia del resto de esta
 *   lista— su ortografía no tiene una grafía alternativa real y
 *   consolidada que sustituir (el "ae"/"oe" nórdico, la "ß"/"ss" alemana):
 *   la distancia entre sonido y letra es irregular caso por caso (letras
 *   mudas, el mismo dígrafo con varias pronunciaciones), y los chistes de
 *   fusión conocidos en inglés ("Mike Rotch", "Anna Sasin") dependen de un
 *   parecido fonético aproximado, no de una equivalencia ortográfica
 *   verificable. Plegarlo iría a adivinar reglas, no a normalizar una
 *   variante real — el mismo motivo que ya excluye la distancia de
 *   edición en el resto de idiomas.
 * - **Vietnamita**: es de script latino, pero el tono es fonémico (seis
 *   tonos distinguen palabras distintas) y no hay una grafía alternativa
 *   real para plegar sin colapsar significados — el mismo riesgo que ya
 *   evita la distancia de edición en el resto de idiomas.
 * - **Árabe, búlgaro, griego, hebreo, hindi, japonés, coreano, ruso,
 *   tailandés, ucraniano y chino**: su script no es latino, y este
 *   mecanismo (plegar sustituyendo caracteres) no tiene un equivalente
 *   verificable sin una romanización propia — intentarlo sin un hablante
 *   nativo que confirme cada regla sería inventar, no normalizar.
 *
 * Añadir un idioma nuevo a la detección de fusión es añadir su fila aquí,
 * nada más.
 */
final class PhoneticFolderRegistry
{
    private const FOLDERS = [
        'spa' => PhoneticFolder::class,
        'por' => PortuguesePhoneticFolder::class,
        'ita' => ItalianPhoneticFolder::class,
        'fra' => FrenchPhoneticFolder::class,
        'deu' => GermanPhoneticFolder::class,
        'ces' => CzechPhoneticFolder::class,
        'slk' => SlovakPhoneticFolder::class,
        'dan' => DanishPhoneticFolder::class,
        'nor' => NorwegianPhoneticFolder::class,
        'swe' => SwedishPhoneticFolder::class,
        'fin' => FinnishPhoneticFolder::class,
        'hun' => HungarianPhoneticFolder::class,
        'ind' => IndonesianPhoneticFolder::class,
        'tur' => TurkishPhoneticFolder::class,
        'pol' => PolishPhoneticFolder::class,
        'nld' => DutchPhoneticFolder::class,
        'ron' => RomanianPhoneticFolder::class,
    ];

    public static function isSupported(string $language): bool
    {
        return isset(self::FOLDERS[$language]);
    }

    public static function fold(string $language, string $text): string
    {
        $class = self::FOLDERS[$language] ?? null;

        if ($class === null) {
            return $text;
        }

        return $class::fold($text);
    }

    /** @return array<int,string> códigos de idioma con plegado fonético disponible */
    public static function supportedLanguages(): array
    {
        return array_keys(self::FOLDERS);
    }
}
