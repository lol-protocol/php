<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el alemán.
 *
 * Mucho más restringida que la del español o el portugués, a propósito: el
 * alemán no tiene una ambigüedad ortográfica tan sistemática, y varias de
 * sus confusiones reales son bimodales y arriesgadas de plegar a ciegas —
 * la "v" nativa suena como "f" ("Vater"), pero en préstamos suena como "v"
 * ("Vase"); no hay forma de distinguir un caso del otro sin diccionario de
 * origen, así que esta clase no toca la "v" en absoluto. La "h" alemana es
 * sólo a veces muda (alarga la vocal precedente: "Bahn") y a veces no
 * (inicial de palabra: "Hund"): tampoco se toca, por el mismo motivo.
 *
 * Lo que sí es fiable:
 * - "ä", "ö", "ü" y "ß" tienen una grafía alternativa real y consolidada
 *   cuando esos caracteres no están disponibles (ae, oe, ue, ss) — no es una
 *   aproximación, es cómo se escriben esas mismas palabras.
 * - La "w" alemana suena siempre como la "v" del inglés ("Wagen" = "Vagen"),
 *   sin las excepciones que sí tiene la "v" nativa.
 */
class GermanPhoneticFolder extends AbstractPhoneticFolder
{
    private const ACCENTS = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
        'ß' => 'ss',
    ];

    protected static function getAccents(): array { return self::ACCENTS; }

    protected static function applyLanguageRules(string $text): string
    {
        return str_replace('w', 'v', $text);
    }
}
