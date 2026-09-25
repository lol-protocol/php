<?php

declare(strict_types=1);

namespace App\Routing;

class ReservedMatchStrategy implements MatchStrategy
{
    use ResolvesActions;

    public function matches(array $segments, array $config): bool
    {
        return !empty($segments) && isset($config['reserved'][$segments[0]]) && count($segments) <= 2;
    }

    public function resolve(array $segments, array $config): ?array
    {
        $first = $segments[0];
        $entry = $config['reserved'][$first];
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
