<?php

declare(strict_types=1);

namespace App\Routing;

trait ResolvesActions
{
    /**
     * Maps an optional numeric action segment to a controller method name.
     * No segment -> $default; a segment that isn't a configured action -> null
     * (a 404, never a silent fall-back to $default).
     */
    private function resolveAction(array $actions, string|null $code, string $default): string|null
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
