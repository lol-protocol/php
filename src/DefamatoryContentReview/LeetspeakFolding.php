<?php

namespace DefamatoryContentReview;

/**
 * Los pasos que los 17 folders fonéticos hacen siempre, idénticos, antes de
 * aplicar sus propias reglas: minúsculas + trim, sustitución de leetspeak
 * (mapa en Leetspeak, una clase — las constantes de trait exigen PHP 8.2+),
 * el mapa de equivalencias propio del idioma (vacío si el folder no tiene
 * uno, como neerlandés o indonesio) y eliminar espacios, guiones y
 * apóstrofos (para que la fusión nombre+apellido no conserve la pausa que
 * los separaba). Ninguno de los cuatro es una regla fonética de un idioma
 * en particular, por eso viven acá y no en cada folder.
 */
trait LeetspeakFolding
{
    private static function unleet(string $text): string
    {
        return Leetspeak::unleet($text);
    }

    /** fusión: sin pausas ni guiones/apóstrofos */
    private static function stripSeparators(string $text): string
    {
        return preg_replace('/[\s\-\'’]+/u', '', $text);
    }

    /**
     * Pipeline común a los 17 folders: minúsculas, leetspeak, el mapa de
     * equivalencias del idioma (si tiene) y quitar separadores. El orden
     * mapa→separadores es intercambiable en la práctica —ningún mapa de
     * equivalencias toca espacio, guion ni apóstrofo— pero se fija acá para
     * que cada folder no tenga que decidirlo por su cuenta.
     *
     * @param array<string,string> $substitutions mapa propio del idioma (self::ACCENTS, self::NORMALIZE...); vacío si no aplica
     */
    private static function foldBase(string $text, array $substitutions = []): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, $substitutions);

        return self::stripSeparators($text);
    }
}
