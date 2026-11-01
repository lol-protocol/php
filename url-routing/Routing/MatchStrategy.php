<?php

declare(strict_types=1);

namespace App\Routing;

interface MatchStrategy
{
    /**
     * @param list<string> $segments
     * @param array<string, mixed> $config
     */
    public function matches(array $segments, array $config): bool;
    /**
     * @param list<string> $segments
     * @param array<string, mixed> $config
     * @return array{controller: string, method: string, params: array<string, mixed>}|null
     */
    public function resolve(array $segments, array $config): ?array;
}
