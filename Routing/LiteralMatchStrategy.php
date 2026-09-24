<?php

namespace App\Routing;

class LiteralMatchStrategy implements MatchStrategy
{
    public function matches(array $segments, array $config): bool
    {
        return isset($config['literal']);
    }

    public function resolve(array $segments, array $config): ?array
    {
        $joined = implode('/', $segments);

        if (isset($config['literal'][$joined])) {
            return $config['literal'][$joined] + ['params' => []];
        }

        return null;
    }
}
