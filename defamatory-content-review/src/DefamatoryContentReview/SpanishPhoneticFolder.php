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
class SpanishPhoneticFolder extends AbstractPhoneticFolder
{
    protected static function getAccents(): array
    {
        return CommonPhoneticAccents::withExtras(['ñ' => 'n', 'ç' => 'c']);
    }

    protected static function applyLanguageRules(string $text): string
    {
        $text = str_replace('ch', "\x01", $text);
        $text = str_replace('qu', 'k', $text);
        $text = preg_replace('/g(?=[ei])/u', '', $text);
        $text = str_replace('j', '', $text);
        $text = str_replace('h', '', $text);
        $text = str_replace('v', 'b', $text);
        $text = preg_replace('/c(?=[ei])/u', 's', $text);
        $text = str_replace('z', 's', $text);
        $text = str_replace('ll', 'y', $text);
        return str_replace("\x01", 'ch', $text);
    }
}
