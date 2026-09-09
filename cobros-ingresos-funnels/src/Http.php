<?php

declare(strict_types=1);

namespace App;

final class Http
{
    /** true si el request llego por HTTPS, directo o detras de un proxy con X-Forwarded-Proto. */
    public static function esSegura(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
