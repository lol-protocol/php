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
class NorwegianPhoneticFolder extends AbstractPhoneticFolder
{
    private const ACCENTS = [
        'æ' => 'ae', 'ø' => 'oe', 'å' => 'aa',
    ];

    protected static function getAccents(): array { return self::ACCENTS; }

    protected static function applyLanguageRules(string $text): string { return $text; }
}
