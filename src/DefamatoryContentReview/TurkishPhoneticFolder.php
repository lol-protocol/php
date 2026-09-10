<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el turco.
 *
 * El turco tiene varios caracteres que no existen en el teclado ASCII
 * estándar, y la sustitución al escribir sin ellos es una convención real
 * y extremadamente común, no una invención: "ı" (sin punto, /ɯ/) se escribe
 * como "i" cuando no hay teclado turco; "ş", "ç", "ö", "ü" se escriben
 * "s", "c", "o", "u"; y "ğ" (yumuşak g, que alarga la vocal anterior y en
 * muchas posiciones apenas se pronuncia) se transcribe como "g" en la
 * práctica internacional habitual — así aparece "Erdoğan" escrito
 * "Erdogan" en cualquier medio que no soporte el carácter, nunca como
 * "Erdoan".
 *
 * "İ" (con punto, mayúscula de "i") y "I" (sin punto, mayúscula de "ı") son
 * letras distintas en turco, pero al plegar todo a minúsculas antes de
 * aplicar estas reglas esa distinción ya no aplica aquí.
 */
class TurkishPhoneticFolder
{
    use LeetspeakFolding;

    private const ACCENTS = [
        'ı' => 'i', 'ş' => 's', 'ç' => 'c', 'ö' => 'o', 'ü' => 'u', 'ğ' => 'g',
    ];

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, self::ACCENTS);

        return preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos
    }
}
