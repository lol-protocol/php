<?php

namespace DefamatoryContentReview;

/**
 * Los dos pasos que los 17 folders fonéticos hacen siempre, idénticos, antes
 * de aplicar sus propias reglas: sustitución de leetspeak (mapa en
 * Leetspeak, una clase — las constantes de trait exigen PHP 8.2+) y
 * eliminar espacios, guiones y apóstrofos (para que la fusión
 * nombre+apellido no conserve la pausa que los separaba). Ninguno de los
 * dos es una regla fonética de un idioma en particular, por eso viven acá y
 * no en cada folder.
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
     * Pipeline base para los folders que, además de acentos, tienen alguna
     * regla fonética propia (Francés, Alemán, Húngaro, Polaco, Portugués,
     * Español, Neerlandés, Indonesio, Rumano): minúsculas, leetspeak, el
     * mapa de equivalencias del idioma (si tiene) y quitar separadores. Los
     * folders sin reglas propias más allá de los acentos usan
     * AccentOnlyPhoneticFolding en su lugar, que preserva el orden
     * mapa/separadores que cada uno ya tenía (intercambiable en la
     * práctica —ningún mapa de equivalencias toca espacio, guion ni
     * apóstrofo— pero no unificado ahí para no perder esa distinción).
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
