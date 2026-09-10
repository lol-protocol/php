<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el finlandés.
 *
 * A diferencia del alemán o el sueco, "ä" y "ö" en finlandés no son
 * variantes de "a"/"o" con una grafía alternativa "ae"/"oe" codificada:
 * son vocales frontales con fonema propio, y la práctica real cuando el
 * carácter no está disponible (nombres de usuario, sistemas antiguos,
 * teclados no finlandeses) es simplificar a la vocal base "a"/"o" sin más
 * — no expandir a un dígrafo que el finlandés no usa para eso.
 *
 * No se pliega la duplicación de vocales o consonantes (kk, aa, uu...): en
 * finlandés la duplicación marca duración y cambia el significado
 * sistemáticamente ("tuli" = fuego, "tuuli" = viento); colapsarla
 * produciría el mismo tipo de colisión falsa masiva que ya se documentó
 * como límite deliberado para la distancia de edición en español.
 */
class FinnishPhoneticFolder
{
    use LeetspeakFolding;

    private const ACCENTS = [
        'ä' => 'a', 'ö' => 'o',
    ];

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, self::ACCENTS);

        return preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos
    }
}
