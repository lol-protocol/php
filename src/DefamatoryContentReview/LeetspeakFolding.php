<?php

namespace DefamatoryContentReview;

/**
 * Los tres pasos que los 17 folders fonéticos hacen siempre, idénticos, antes
 * de aplicar sus propias reglas: sustitución de leetspeak (mapa en Leetspeak,
 * una clase — las constantes de trait exigen PHP 8.2+), quitar las marcas
 * combinantes que el mapa de acentos de cada folder no alcanza, y eliminar
 * espacios, guiones y apóstrofos (para que la fusión nombre+apellido no
 * conserve la pausa que los separaba). Ninguno de los tres es una regla
 * fonética de un idioma en particular, por eso viven acá y no en cada folder.
 */
trait LeetspeakFolding
{
    private static function unleet(string $text): string
    {
        return Leetspeak::unleet($text);
    }

    /**
     * Cada folder tiene su mapa de acentos, y todos parten de la forma
     * precompuesta: en NFD no coincide ninguno. Sin este paso, "Elba Gína"
     * escrito con acento combinante plegaba a "elbagína" en vez de
     * "elbagina" y la fusión no se detectaba. Ver AccentFolding.
     */
    private static function stripCombiningMarks(string $text): string
    {
        return AccentFolding::stripCombiningMarks($text);
    }

    /** fusión: sin pausas ni guiones/apóstrofos */
    private static function stripSeparators(string $text): string
    {
        return preg_replace('/[\s\-\'’]+/u', '', $text);
    }
}
