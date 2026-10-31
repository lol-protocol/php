<?php

declare(strict_types=1);

namespace App\Routing;

class ByLengthMatchStrategy implements MatchStrategy
{
    use ResolvesActions;

    /**
     * @param list<string> $segments
     * @param array<string, mixed> $config
     */
    public function matches(array $segments, array $config): bool
    {
        return !empty($segments) && ctype_digit($segments[0]);
    }

    /**
     * @param list<string> $segments
     * @param array<string, mixed> $config
     * @return array{controller: string, method: string, params: array<string, mixed>}|null
     */
    public function resolve(array $segments, array $config): ?array
    {
        if (count($segments) > 2) {
            return null;
        }

        $id = $segments[0];
        $entry = $config['by_length'][strlen($id)] ?? null;

        if (!$entry) {
            return null;
        }

        $actionCode = $segments[1] ?? null;
        $method = $this->resolveAction($entry['actions'] ?? [], $actionCode, 'show');

        if ($method === null) {
            return null;
        }

        return [
            'controller' => $entry['controller'],
            'method' => $method,
            'params' => ['id' => $id],
        ];
    }
}
