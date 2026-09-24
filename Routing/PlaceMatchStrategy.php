<?php

namespace App\Routing;

class PlaceMatchStrategy implements MatchStrategy
{
    public function matches(array $segments, array $config): bool
    {
        if (!isset($config['place']) || empty($segments)) {
            return false;
        }

        $first = $segments[0];
        return ctype_alpha($first);
    }

    public function resolve(array $segments, array $config): ?array
    {
        $entry = $config['place'];
        $codes = $segments;
        $actionCode = null;

        if (ctype_digit(end($codes))) {
            $actionCode = array_pop($codes);
        }

        if (empty($codes) || count($codes) > 3) {
            return null;
        }

        foreach ($codes as $code) {
            if (!ctype_alpha($code)) {
                return null;
            }
        }

        $method = $this->resolveAction($entry['actions'] ?? [], $actionCode);

        if ($method === null) {
            return null;
        }

        return [
            'controller' => $entry['controller'],
            'method' => $method,
            'params' => ['codes' => $codes],
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
