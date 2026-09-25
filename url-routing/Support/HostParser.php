<?php

declare(strict_types=1);

namespace App\Support;

class HostParser
{
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

    public static function getLocale(string $host, array $supportedLocales = ['spa', 'eng']): string
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
