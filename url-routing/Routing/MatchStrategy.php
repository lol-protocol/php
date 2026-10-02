<?php

declare(strict_types=1);

namespace App\Routing;

interface MatchStrategy
{
    public function matches(array $segments, array $config): bool;
    public function resolve(array $segments, array $config): ?array;
}
