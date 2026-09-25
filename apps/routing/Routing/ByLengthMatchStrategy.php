<?php

declare(strict_types=1);

namespace App\Routing;

class ByLengthMatchStrategy implements MatchStrategy
{
    public function matches(array $segments, array $config): bool
    {
        return !empty($segments) && ctype_digit($segments[0]);
    }

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
        $method = $this->resolveAction($entry['actions'] ?? [], $actionCode);

        if ($method === null) {
            return null;
        }

        return [
            'controller' => $entry['controller'],
            'method' => $method,
            'params' => ['id' => $id],
        ];
    }

    private function resolveAction(array $actions, string|null $code, string $default = 'show'): string|null
    {
        if ($code === null) {
            return $default;
        }

        if (!ctype_digit($code)) {
            return null;
        }

        return $actions[(int) $code] ?? null;
    }
}
