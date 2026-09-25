<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el noruego.
 *
 * Misma base que el danés — no por casualidad: el bokmål noruego se
 * escribía como una variante del danés hasta bien entrado el siglo XX.
 * "æ", "ø" y "å" tienen la misma grafía alternativa real y consolidada
 * (ae/oe/aa), usada históricamente y todavía hoy en apellidos y topónimos
 * cuando esos caracteres no están disponibles.
 *
 * No se modela ninguna otra confusión: el noruego (bokmål) es bastante
 * regular fuera de esos tres caracteres, y no hay una ambigüedad
 * ortográfica sistemática adicional que valga la pena plegar.
 */
class NorwegianPhoneticFolder
{
    use AccentOnlyPhoneticFolding;

    private const ACCENTS = [
        'æ' => 'ae', 'ø' => 'oe', 'å' => 'aa',
    ];

    public static function fold(string $text): string
    {
        return self::foldAccentsThenSeparators($text);
    }
}
