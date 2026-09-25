<?php

declare(strict_types=1);

namespace App\Support;

class HttpSecurityHeaders
{
    public static function setSecurityHeaders(): void
    {
        self::enableCompression();
        self::setSecurityPolicies();
    }

    private static function enableCompression(): void
    {
        if (!headers_sent()) {
            // Enable gzip compression for responses > 1KB
            if (extension_loaded('zlib') && !ini_get('output_handler')) {
                ob_start('ob_gzhandler');
                header('Vary: Accept-Encoding');
            }
        }
    }

    private static function setSecurityPolicies(): void
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
