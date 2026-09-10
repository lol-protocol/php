<?php

namespace DefamatoryContentReview;

/**
 * Normalización fonética para el italiano.
 *
 * El italiano no tiene la ambigüedad ortográfica sistemática del español o
 * el portugués: no confunde b/v, ni s/z/c suave del mismo modo (su "z" no
 * compite con una "s" que suene igual en las mismas posiciones), y su "h"
 * sólo endurece la "c"/"g" precedente (che, chi, ghe, ghi) — nunca es muda
 * por sí sola, así que quitarla cambiaría el sonido en vez de preservarlo.
 * Su ortografía es, en conjunto, mayormente transparente.
 *
 * Por eso esta clase se limita a lo que hace falta para que funcione la
 * detección de fusión — minúsculas, sin acentos, sin espacios ni guiones —
 * sin inventar plegados que no responden a una confusión real y que sólo
 * aumentarían el riesgo de falsos positivos.
 */
class ItalianPhoneticFolder
{
    use LeetspeakFolding;

    private const ACCENTS = [
        'à' => 'a', 'á' => 'a',
        'è' => 'e', 'é' => 'e',
        'ì' => 'i', 'í' => 'i',
        'ò' => 'o', 'ó' => 'o',
        'ù' => 'u', 'ú' => 'u',
    ];

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, self::ACCENTS);

        return preg_replace('/[\s\-\'’]+/u', '', $text);
    }
}
