<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el sueco.
 *
 * Misma familia de grafías alternativas reales que danés, noruego y
 * alemán: "å" viene históricamente de escribir "aa" (el propio signo es
 * una "a" pequeña sobre otra "a", no un accente decorativo), y "ä"/"ö" se
 * expanden a "ae"/"oe" en el mismo contexto de siempre — pasaportes,
 * teclados sin esos caracteres, apellidos suecos transcritos al inglés
 * ("Åberg" → "Aaberg" o "Aberg" según la fuente, nunca al azar).
 *
 * No se toca ninguna otra letra: el sueco no tiene una confusión
 * ortográfica sistemática comparable fuera de esas tres vocales.
 */
class SwedishPhoneticFolder extends AbstractPhoneticFolder
{
    private const ACCENTS = [
        'ä' => 'ae', 'ö' => 'oe', 'å' => 'aa',
    ];

    protected static function getAccents(): array { return self::ACCENTS; }

    protected static function applyLanguageRules(string $text): string { return $text; }
}
