<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el portugués.
 *
 * Pliega grafías que suenan igual: "ç" y "c" ante e/i suenan como "s" (y el
 * portugués, sobre todo el brasileño, confunde con frecuencia s/z en esa
 * misma posición: "cozer"/"coser" son casi homófonas incluso para hablantes
 * nativos), la "h" muda, y protege los dígrafos con sonido propio ("lh"
 * palatal, "nh" palatal, "ch" que en portugués suena como "sh" inglesa, no
 * como la africada del español) para que el resto de reglas no los destruya.
 *
 * A diferencia del español, aquí NO se unifican b/v (son sonidos distintos
 * en portugués) ni se elimina la "g" suave / "j" (es una consonante audible,
 * como la "j" francesa, no una aspiración que se apaga): se normaliza a un
 * símbolo común en vez de borrarse.
 *
 * No es un modelo fonológico completo — en particular no modela las vocales
 * nasales (ão, õe...) más allá de quitarles la tilde: cubre exactamente las
 * confusiones que en portugués producen coincidencias reales para este
 * módulo, nada más.
 */
class PortuguesePhoneticFolder
{
    use LeetspeakFolding;

    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'ê' => 'e',
        'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ü' => 'u',
    ];

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, self::ACCENTS);
        $text = preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos

        // Dígrafos con sonido propio: se protegen antes de tocar sus letras sueltas.
        $text = str_replace(['lh', 'nh', 'ch'], ["\x01", "\x02", "\x03"], $text);

        $text = str_replace('ç', 's', $text);
        $text = preg_replace('/c(?=[ei])/u', 's', $text);  // ce, ci: mismo sonido que la s
        $text = str_replace('qu', 'k', $text);
        $text = preg_replace('/g(?=[ei])/u', 'j', $text);   // sonido audible: se unifica, no se borra
        $text = str_replace('h', '', $text);                 // h suelta, muda
        $text = str_replace('z', 's', $text);

        return str_replace(["\x01", "\x02", "\x03"], ['lh', 'nh', 'ch'], $text);
    }
}
