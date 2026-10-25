<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A conditional GET (RFC 9110, section 13): whether the copy a client already has is still the
 * current one, so the answer can be a 304 with no body.
 */
final class RespuestaCondicional
{
    /**
     * @param string|null $siNoCoincide the request's If-None-Match
     * @param string|null $siModificadoDesde the request's If-Modified-Since
     * @param string $etag the current ETag, quoted
     * @param int|null $desde since when the current copy is what it is (Unix time), if known
     */
    public static function sinCambios(?string $siNoCoincide, ?string $siModificadoDesde, string $etag, ?int $desde): bool
    {
        if ($siNoCoincide !== null && trim($siNoCoincide) !== '') {
            foreach (explode(',', $siNoCoincide) as $candidata) {
                $candidata = trim($candidata);
                // A weak validator (W/"…") is good enough for a GET.
                if ($candidata === '*' || preg_replace('#^W/#', '', $candidata) === $etag) {
                    return true;
                }
            }
            // With If-None-Match present, If-Modified-Since is ignored (13.1.3).
            return false;
        }

        if ($siModificadoDesde === null || $desde === null) {
            return false;
        }
        $fecha = strtotime($siModificadoDesde);

        return $fecha !== false && $desde <= $fecha;
    }

    /** The HTTP date of a Unix time, as Last-Modified wants it. */
    public static function fechaHttp(int $momento): string
    {
        return gmdate('D, d M Y H:i:s', $momento) . ' GMT';
    }
}
