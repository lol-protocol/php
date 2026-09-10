<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el danés.
 *
 * Igual que en el folder alemán, "æ", "ø" y "å" tienen una grafía
 * alternativa real y consolidada, no una aproximación inventada: "å" es
 * oficialmente "aa" (así se escribía antes de la reforma de 1948, y sigue
 * siendo válida y común en apellidos y topónimos — "Kierkegaard" nunca pasó
 * a escribirse con "å"), y "æ"/"ø" se expanden a "ae"/"oe" en el mismo
 * contexto (pasaportes, sistemas sin esos caracteres).
 *
 * No se toca la "d" suave (muda tras vocal en muchas palabras, p. ej.
 * "mad") ni el "stød" (golpe de glotis): ninguno de los dos tiene una
 * grafía alternativa sistemática — intentar plegarlos sería adivinar, no
 * normalizar.
 */
class DanishPhoneticFolder
{
    use LeetspeakFolding;

    private const ACCENTS = [
        'æ' => 'ae', 'ø' => 'oe', 'å' => 'aa',
    ];

    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = self::unleet($text);
        $text = strtr($text, self::ACCENTS);

        return preg_replace('/[\s\-\'’]+/u', '', $text); // fusión: sin pausas ni guiones/apóstrofos
    }
}
