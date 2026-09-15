<?php

namespace DefamatoryContentReview;

/**
 * Los dos pasos que los 17 folders fonéticos hacen siempre, idénticos, antes
 * de aplicar sus propias reglas: sustitución de leetspeak (mapa en Leetspeak,
 * una clase — las constantes de trait exigen PHP 8.2+) y eliminar espacios,
 * guiones y apóstrofos (para que la fusión nombre+apellido no conserve la
 * pausa que los separaba). Ninguno de los dos es una regla fonética de un
 * idioma en particular, por eso viven acá y no en cada folder.
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
}
