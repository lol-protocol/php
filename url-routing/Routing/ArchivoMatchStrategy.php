<?php

declare(strict_types=1);

namespace App\Routing;

/**
 * File names: a path whose last segment has a dot (/robots.txt,
 * /sitemap/10/1.xml). The `archivos` config is keyed by the first segment:
 * a key with a dot is that exact file; a key without one is a folder whose
 * controller gets the rest of the path as params['ruta'] and validates it.
 */
class ArchivoMatchStrategy implements MatchStrategy
{
    private const MAX_SEGMENTOS = 3;

    /**
     * @param list<string> $segments
     * @param array<string, mixed> $config
     */
    public function matches(array $segments, array $config): bool
    {
        return isset($config['archivos'][$segments[0] ?? ''])
            && str_contains((string)end($segments), '.');
    }

    /**
     * @param list<string> $segments
     * @param array<string, mixed> $config
     * @return array{controller: string, method: string, params: array<string, mixed>}|null
     */
    public function resolve(array $segments, array $config): ?array
    {
        $entry = $config['archivos'][$segments[0]];
        $resto = array_slice($segments, 1);

        if (str_contains($segments[0], '.') ? $resto !== [] : ($resto === [] || count($segments) > self::MAX_SEGMENTOS)) {
            return null;
        }

        return [
            'controller' => $entry['controller'],
            'method' => $entry['method'],
            'params' => ['ruta' => $resto],
        ];
    }
}
