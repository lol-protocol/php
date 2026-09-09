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
