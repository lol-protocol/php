<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el checo.
 *
 * Su única ambigüedad realmente sistemática — la que cualquier hablante
 * nativo aprende de memoria en la escuela porque el oído no la resuelve —
 * es "y" vs. "i" (y "ý" vs. "í"): ambos pares suenan exactamente igual en
 * checo estándar, y cuál se escribe depende de reglas gramaticales
 * históricas (dureza de la consonante precedente), no de la pronunciación.
 * Por eso "y"/"ý" se pliegan al mismo símbolo que "i"/"í".
 *
 * El resto de vocales largas (á, é, ó, ú, ů) son fonémicamente distintas de
 * su versión corta — la longitud sí cambia el significado en checo — así
 * que aquí sólo se les quita la marca diacrítica como normalización de
 * accesibilidad (igual que en los demás folders), no como afirmación de que
 * suenan igual a la vocal corta. "ú" y "ů" son la misma vocal larga escrita
 * distinto según su posición en la palabra (regla ortográfica, no
 * fonética): se unifican.
 */
class CzechPhoneticFolder extends AbstractPhoneticFolder
{
    private const ACCENTS = [
        'á' => 'a', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ó' => 'o',
        'ú' => 'u', 'ů' => 'u',
        'ý' => 'i', 'y' => 'i',
    ];

    /** @return array<string,string> */
    protected static function getAccents(): array { return self::ACCENTS; }

    protected static function applyLanguageRules(string $text): string
    {
        return $text;
    }
}
