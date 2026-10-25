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
            // Enable gzip compression for responses > 1KB. ob_gzhandler sends `Vary: Accept-Encoding`
            // itself, on every response whether or not it compresses it: sending it here too made
            // each response carry the header twice.
            if (extension_loaded('zlib') && !ini_get('output_handler')) {
                ob_start('ob_gzhandler');
            }
        }
    }

    public static function isHttps(): bool
    {
        // SERVER_PORT arrives as a string, so it must be cast before comparing.
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    private static function setSecurityPolicies(): void
    {
        $isSecure = self::isHttps();

        // What PHP's session cache limiter used to send, and only on requests that started a session. Now it
        // is sent on all of them: pages are not cacheable, so a withdrawal takes effect at once, and a page
        // the visitor is refused is no-store like the rest (see AccessPolicy::ocultar()). The owner's
        // responses replace Cache-Control with `private, no-store` (AccessPolicy::marcarPrivada()).
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');

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
