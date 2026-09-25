<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el neerlandés.
 *
 * Dos pares de dígrafos son homófonos reales y muy conocidos en neerlandés
 * — de los que se enseñan explícitamente en la escuela porque no hay forma
 * de distinguirlos de oído:
 *
 * - "ei" e "ij" suenan exactamente igual (/ɛi/). Es la confusión
 *   ortográfica más famosa del idioma.
 * - "au" y "ou" suenan exactamente igual (/ʌu/).
 *
 * Ambos pares se unifican a una de sus dos formas. No se modela ninguna
 * otra confusión: fuera de esos dos pares, la ortografía neerlandesa es
 * bastante regular.
 */
class DutchPhoneticFolder extends AbstractPhoneticFolder
{
    /** @return array<string,string> */
    protected static function getAccents(): array { return []; }

    protected static function applyLanguageRules(string $text): string
    {
        $text = str_replace('ij', 'ei', $text);
        return str_replace('ou', 'au', $text);
    }
}
