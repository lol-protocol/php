<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el indonesio.
 *
 * No es una fonética de acentos: es la reforma ortográfica de 1972 (Ejaan
 * Yang Disempurnakan), que cambió cómo se escriben sonidos que no
 * cambiaron de pronunciación. Muchísimos nombres propios indonesios siguen
 * usando la grafía antigua ("Soekarno", "Achmad", "Jusuf") junto a la
 * moderna ("Sukarno", "Akhmad", "Yusuf") — son la misma palabra, el mismo
 * sonido, dos ortografías vigentes a la vez. Sin normalizar esto, el mismo
 * apellido en grafía distinta no coincidiría con una entrada del
 * diccionario escrita en la otra.
 *
 * Mapeo antiguo → moderno: "oe" (sistema Van Ophuijsen, anterior a 1947,
 * el ejemplo más conocido del idioma — "Soekarno"/"Sukarno") → "u"; "dj" →
 * "j"; "tj" → "c"; "nj" → "ny"; "sj" → "sy"; "ch" → "kh"; y la "j" antigua
 * suelta (no parte de "dj") → "y". El orden importa: "dj" se protege antes
 * de que la regla general de "j" suelta la reinterprete como "y".
 */
class IndonesianPhoneticFolder
{
    use LeetspeakFolding;

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos

        $text = str_replace('oe', 'u', $text); // grafía Van Ophuijsen: "oe" = /u/

        // "dj" (grafía antigua de "j") se protege antes de tocar la "j" suelta.
        $text = str_replace('dj', "\x01", $text);

        $text = str_replace('tj', 'c', $text);
        $text = str_replace('nj', 'ny', $text);
        $text = str_replace('sj', 'sy', $text);
        $text = str_replace('ch', 'kh', $text);
        $text = str_replace('j', 'y', $text); // "j" antigua suelta sonaba /y/

        return str_replace("\x01", 'j', $text);
    }
}
