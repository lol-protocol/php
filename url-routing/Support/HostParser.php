<?php

declare(strict_types=1);

namespace App\Support;

class HostParser
{
    /** Language subdomains (ISO 639-2, three letters) and their hreflang code (ISO 639-1). */
    public const IDIOMAS = ['spa' => 'es', 'eng' => 'en'];

    public static function parse(string $host): array
    {
        $hostWithoutPort = explode(':', $host)[0];
        $parts = explode('.', $hostWithoutPort);

        return [
            'host_without_port' => $hostWithoutPort,
            'parts' => $parts,
        ];
    }

    public static function matchesDomain(string $host, array $domains): string|null
    {
        $parsed = self::parse($host);
        $hostWithoutPort = $parsed['host_without_port'];

        foreach ($domains as $domain) {
            if ($hostWithoutPort === $domain || str_ends_with($hostWithoutPort, '.' . $domain)) {
                return $domain;
            }
        }

        return null;
    }

    public static function getLocale(string $host, ?array $supportedLocales = null): string
    {
        $supportedLocales ??= array_keys(self::IDIOMAS);
        $parsed = self::parse($host);
        $parts = $parsed['parts'];
        $sub = strtolower($parts[0] ?? '');

        // Only treat as locale if there are multiple parts (not just the domain itself)
        if (count($parts) > 2 && in_array($sub, $supportedLocales, true)) {
            return $sub;
        }

        return 'spa';
    }

    /** This request's scheme://host, for absolute URLs. Only a plain host name is trusted from the Host header. */
    public static function origen(): string
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if (preg_match('/^[a-z0-9.-]+(:\d+)?$/', $host) !== 1) {
            $host = 'localhost';
        }

        return (HttpSecurityHeaders::isHttps() ? 'https' : 'http') . '://' . $host;
    }

    /**
     * The same $ruta in every language: hreflang code => absolute URL, plus
     * x-default on the bare domain (which serves the default language).
     *
     * @return array<string, string>
     */
    public static function alternos(string $ruta): array
    {
        [$esquema, $host] = explode('://', self::origen(), 2);
        $parts = explode('.', $host);
        if (count($parts) > 2 && isset(self::IDIOMAS[$parts[0]])) {
            array_shift($parts);
        }
        $base = implode('.', $parts);

        $urls = [];
        foreach (self::IDIOMAS as $subdominio => $hreflang) {
            $urls[$hreflang] = "{$esquema}://{$subdominio}.{$base}{$ruta}";
        }
        $urls['x-default'] = "{$esquema}://{$base}{$ruta}";

        return $urls;
    }
}
