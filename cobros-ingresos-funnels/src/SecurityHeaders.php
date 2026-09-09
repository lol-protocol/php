<?php

declare(strict_types=1);

namespace App;

/**
 * Headers de seguridad basicos para cada respuesta. Devuelve un array en vez
 * de llamar header() directo para que sea testeable (header() es un no-op
 * silencioso bajo el SAPI de CLI, con lo que un test no podria verificar
 * nada real); el front controller es el que efectivamente los manda.
 */
final class SecurityHeaders
{
    /** @return array<string,string> nombre de header => valor */
    public static function listado(): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // La app no usa JavaScript en ningun lado, asi que script-src va
            // directo a 'none'. Los graficos SVG y algunos layouts puntuales
            // usan atributos style="" inline, de ahi el 'unsafe-inline' en
            // style-src (no en script-src).
            'Content-Security-Policy' => "default-src 'self'; style-src 'self' 'unsafe-inline'; "
                . "script-src 'none'; img-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
        ];

        if (Http::esSegura()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }
}
