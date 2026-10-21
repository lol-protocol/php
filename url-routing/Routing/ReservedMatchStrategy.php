<?php

declare(strict_types=1);

namespace App\Routing;

/**
 * An exact first segment ('0', and the 1-letter functions). Normally it takes
 * at most one numeric action; an entry with `ruta` => N instead hands up to N
 * more segments to its controller as params['ruta'], to validate itself.
 */
class ReservedMatchStrategy implements MatchStrategy
{
    use ResolvesActions;

    public function matches(array $segments, array $config): bool
    {
        return !empty($segments) && isset($config['reserved'][$segments[0]]);
    }

    public function resolve(array $segments, array $config): ?array
    {
        $first = $segments[0];
        $entry = $config['reserved'][$first];

        if (isset($entry['ruta'])) {
            $resto = array_slice($segments, 1);
            return count($resto) > $entry['ruta'] ? null : [
                'controller' => $entry['controller'],
                'method' => $entry['method'],
                'params' => ['ruta' => $resto],
            ];
        }

        if (count($segments) > 2) {
            return null;
        }
        $actionCode = $segments[1] ?? null;
        $method = $this->resolveAction($entry['actions'] ?? [], $actionCode, $entry['method'] ?? 'index');

        if ($method === null) {
            return null;
        }

        return [
            'controller' => $entry['controller'],
            'method' => $method,
            'params' => [],
        ];
    }
}
