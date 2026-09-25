<?php

declare(strict_types=1);

namespace App\Support;

use App\Routing\LiteralMatchStrategy;
use App\Routing\OrderMatchStrategy;
use App\Routing\ReservedMatchStrategy;
use App\Routing\ByLengthMatchStrategy;
use App\Routing\PlaceMatchStrategy;
use App\Routing\ConfigValidator;

/**
 * URL Router — dispatches by the SHAPE of the first path segment
 * instead of by matching words against named patterns:
 *
 *   - all digits  -> digit count selects the resource type
 *   - all letters -> place hierarchy (pais/region/ciudad)
 *   - exact word  -> a literal or reserved system path
 *
 * A second numeric segment, when present, selects a sub-action on
 * the matched resource. An action segment that doesn't map to
 * anything is a 404, not a silent fall-back to the base resource —
 * and so is any segment beyond what a type's shape allows (extra
 * trailing garbage, a 4th place level). Nothing is silently ignored.
 *
 * Contract: numeric ids MUST be generated zero-padded to their
 * type's fixed digit width (see `enlace()` in index.php) — an
 * unpadded id silently resolves as a different, shorter type.
 *
 * dispatch() always RETURNS the response body (never echoes it) —
 * on every path, success or error — so the caller must echo it.
 */

class Router
{
    protected $config = [];
    protected $segments = [];
    protected $strategies = [];

    public function __construct()
    {
        $this->segments = $this->parseSegments($_SERVER['REQUEST_URI'] ?? '/');
        $this->initializeStrategies();
    }

    protected function initializeStrategies(): void
    {
        $this->strategies = [
            new ByLengthMatchStrategy(),      // Most common (numeric IDs: /1234/)
            new PlaceMatchStrategy(),          // Common (places: /mx/jalisco/)
            new LiteralMatchStrategy(),        // Less common (exact paths)
            new OrderMatchStrategy(),          // Specific (/order/)
            new ReservedMatchStrategy(),       // Edge case (/0/)
        ];
    }

    protected function parseSegments(string $uri): array
    {
        $path = trim((string)strtok($uri, '?'), '/');

        if ($path === '') {
            return [];
        }

        return array_map('strtolower', explode('/', $path));
    }

    public function loadConfig(string $configFile): void
    {
        $this->config = require $configFile;
        ConfigValidator::validate($this->config);
    }

    public function typeLength(string $type): int|null
    {
        foreach ($this->config['by_length'] ?? [] as $length => $entry) {
            if ($entry['type'] === $type) {
                return $length;
            }
        }

        return null;
    }

    public function dispatch(): string
    {
        $match = $this->resolve();

        if (!$match) {
            return $this->handleNotFound();
        }

        return $this->callController($match);
    }

    protected function resolve(): array|null
    {
        if (empty($this->segments)) {
            $entry = $this->config['reserved'][''] ?? null;
            return $entry ? $entry + ['params' => []] : null;
        }

        foreach ($this->strategies as $strategy) {
            if ($strategy->matches($this->segments, $this->config)) {
                $match = $strategy->resolve($this->segments, $this->config);
                if ($match !== null) {
                    return $match;
                }
            }
        }

        return null;
    }

    protected function callController(array $match): string
    {
        $fullClass = 'App\\Controllers\\' . $match['controller'];

        if (!class_exists($fullClass)) {
            return $this->handleError("Controller not found: $fullClass");
        }

        try {
            $controller = new $fullClass();
            $method = $match['method'];

            if (!method_exists($controller, $method)) {
                return $this->handleError("Method not found: {$method}");
            }

            return (string)call_user_func([$controller, $method], $match['params']);
        } catch (\Throwable $e) {
            return $this->handleError("Controller error: " . $e->getMessage());
        }
    }

    protected function handleNotFound(): string
    {
        http_response_code(404);
        return '<h1>404 - Pagina no encontrada</h1>';
    }

    protected function handleError(string $message): string
    {
        http_response_code(500);
        return '<h1>500 - ' . htmlspecialchars($message) . '</h1>';
    }
}
