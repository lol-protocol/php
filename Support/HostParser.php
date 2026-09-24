<?php

namespace App\Support;

class HostParser
{
    /**
     * Parse host header into domain (without port) and subdomain.
     * Returns ['domain' => string, 'subdomain' => string|null, 'host_without_port' => string]
     */
    public static function parse($host)
    {
        $hostWithoutPort = explode(':', $host)[0];
        $parts = explode('.', $hostWithoutPort);

        return [
            'host_without_port' => $hostWithoutPort,
            'parts' => $parts,
        ];
    }

    /**
     * Check if host is or belongs to one of the given domains.
     * "evilcontrastocolor.local" should NOT match "contrastocolor.local".
     */
    public static function matchesDomain($host, array $domains)
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

    /**
     * Get 3-letter subdomain if it matches the whitelist.
     */
    public static function getLocale($host, array $supportedLocales = ['spa', 'eng'])
    {
        $parsed = self::parse($host);
        $parts = $parsed['parts'];
        $sub = strtolower($parts[0] ?? '');

        // Only treat as locale if there are multiple parts (not just the domain itself)
        if (count($parts) > 2 && in_array($sub, $supportedLocales, true)) {
            return $sub;
        }

        return 'spa';
    }
}
