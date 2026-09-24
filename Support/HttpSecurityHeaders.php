<?php

declare(strict_types=1);

namespace App\Support;

class HttpSecurityHeaders
{
    public static function setSecurityHeaders(): void
    {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                   $_SERVER['SERVER_PORT'] === 443;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if ($isSecure) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: https:; " .
               "font-src 'self'; " .
               "connect-src 'self'; " .
               "form-action 'self'; " .
               "frame-ancestors 'none';";

        header("Content-Security-Policy: {$csp}");

        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}
