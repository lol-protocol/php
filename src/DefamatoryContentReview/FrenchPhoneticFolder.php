<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el francés.
 *
 * Pliega grafías que suenan igual: "ç" y "c" ante e/i/y suenan como "s", "ph"
 * suena "f", y protege el dígrafo "ch" (sonido "sh", no la africada del
 * español) para que el resto de reglas no lo toque. La "g" suave (ge, gi,
 * gy) es, como en portugués, una consonante audible (el sonido de la "j"
 * francesa) — se normaliza a un símbolo común en vez de borrarse.
 *
 * La "h" francesa sí es siempre muda (a diferencia del alemán, donde sólo
 * endurece una consonante precedente), así que aquí sí se elimina sin más
 * excepción que la protegida dentro de "ch".
 *
 * No modela la nasalización vocálica (an, en, in, on...) ni las consonantes
 * finales mudas, tan características del francés: intentarlo sin poder
 * verificar caso por caso arriesgaba más colisiones falsas de las que
 * resolvía. Cubre exactamente las confusiones ortográficas reales para este
 * módulo, nada más.
 */
class FrenchPhoneticFolder
{
    use LeetspeakFolding;

    private const ACCENTS = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'ö' => 'o',
        'û' => 'u', 'ù' => 'u', 'ü' => 'u',
        'ÿ' => 'y',
        'œ' => 'oe', 'æ' => 'ae',
    ];

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, self::ACCENTS);
        $text = preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos

        // "ch" es un sonido propio ("sh"): se protege antes de tocar la "c" o la "h" sueltas.
        $text = str_replace('ch', "\x01", $text);

        $text = str_replace('ç', 's', $text);
        $text = preg_replace('/c(?=[eiy])/u', 's', $text);  // ce, ci, cy: mismo sonido que la s
        $text = str_replace('qu', 'k', $text);
        $text = str_replace('ph', 'f', $text);
        $text = preg_replace('/g(?=[eiy])/u', 'j', $text);  // ge, gi, gy: sonido audible, se unifica
        $text = str_replace('h', '', $text);                 // h muda siempre

        return str_replace("\x01", 'ch', $text);
    }
}
