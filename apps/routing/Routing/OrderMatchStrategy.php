<?php

declare(strict_types=1);

namespace App\Routing;

class OrderMatchStrategy implements MatchStrategy
{
    public function matches(array $segments, array $config): bool
    {
        return isset($config['order']) && !empty($segments) && $segments[0] === 'order';
    }

    public function resolve(array $segments, array $config): ?array
    {
        if (count($segments) > 3) {
            return null;
        }

        $id = $segments[1] ?? null;

        if ($id === null || !ctype_digit($id)) {
            return null;
        }

        $entry = $config['order'];
        $actionCode = $segments[2] ?? null;
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

    private function resolveAction(array $actions, $code, $default = 'show')
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
