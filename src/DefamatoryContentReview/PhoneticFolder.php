<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el español.
 *
 * Pliega grafías que suenan igual para que una variante ortográfica —evasiva
 * o simplemente distinta— coincida con la forma canónica del diccionario:
 * b/v, s/z/c(e,i), ll/y, la h muda, y el sonido aspirado de la jota y de la g
 * suave (ge, gi). No es un modelo fonológico completo: cubre exactamente las
 * confusiones que en español producen coincidencias reales para este módulo,
 * nada más.
 *
 * Implementación específica del español; no se usa para otros idiomas.
 */
class PhoneticFolder
{
    use LeetspeakFolding;

    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    /**
     * Forma fonética canónica: minúsculas, sin acentos, sin espacios (para
     * simular la fusión al pronunciar nombre y apellido sin pausa entre
     * ellos), con las equivalencias sonoras del español unificadas.
     */
    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, self::ACCENTS);
        $text = preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos

        // "ch" es un sonido propio: se protege antes de tocar la "c" o la "h" sueltas.
        $text = str_replace('ch', "\x01", $text);

        $text = str_replace('qu', 'k', $text);
        $text = preg_replace('/g(?=[ei])/u', '', $text); // ge, gi: sonido aspirado
        $text = str_replace('j', '', $text);              // la jota: igual de aspirada
        $text = str_replace('h', '', $text);               // h muda siempre
        $text = str_replace('v', 'b', $text);
        $text = preg_replace('/c(?=[ei])/u', 's', $text);  // ce, ci: mismo sonido que la s
        $text = str_replace('z', 's', $text);
        $text = str_replace('ll', 'y', $text);

        return str_replace("\x01", 'ch', $text);
    }
}
