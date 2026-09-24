<?php

/**
 * URL Router — dispatches by the SHAPE of the first path segment
 * instead of by matching words against named patterns:
 *
 *   - all digits  -> digit count selects the resource type
 *   - all letters -> place hierarchy (pais/region/ciudad)
 *   - exact word  -> a literal or reserved system path
 *
 * A second numeric segment, when present, selects a sub-action on
 * the matched resource (its meaning is scoped to that resource type).
 */

class Router
{
    protected $config = [];
    protected $segments = [];

    public function __construct()
    {
        $this->segments = $this->parseSegments($_SERVER['REQUEST_URI'] ?? '/');
    }

    protected function parseSegments($uri)
    {
        $path = trim(strtok($uri, '?'), '/');
        return $path === '' ? [] : explode('/', $path);
    }

    public function loadConfig($configFile)
    {
        $this->config = require $configFile;
    }

    public function dispatch()
    {
        $match = $this->resolve();

        if (!$match) {
            return $this->handleNotFound();
        }

        return $this->callController($match);
    }

    protected function resolve()
    {
        if (empty($this->segments)) {
            $entry = $this->config['reserved'][''] ?? null;
            return $entry ? $entry + ['params' => []] : null;
        }

        $literal = $this->matchLiteral();
        if ($literal) {
            return $literal;
        }

        if (isset($this->config['order']) && $this->segments[0] === 'order') {
            return $this->matchOrder();
        }

        $first = $this->segments[0];

        if (count($this->segments) === 1 && isset($this->config['reserved'][$first])) {
            return $this->config['reserved'][$first] + ['params' => []];
        }

        if (ctype_digit($first)) {
            return $this->matchByLength($first);
        }

        if (ctype_alpha($first) && isset($this->config['place'])) {
            return $this->matchPlace();
        }

        return null;
    }

    protected function matchLiteral()
    {
        if (!isset($this->config['literal'])) {
            return null;
        }

        $joined = implode('/', $this->segments);

        if (isset($this->config['literal'][$joined])) {
            return $this->config['literal'][$joined] + ['params' => []];
        }

        return null;
    }

    protected function matchOrder()
    {
        $id = $this->segments[1] ?? null;

        if (!$id || !ctype_digit($id)) {
            return null;
        }

        $entry = $this->config['order'];
        $actionCode = $this->segments[2] ?? null;
        $method = $this->resolveAction($entry['actions'] ?? [], $actionCode);

        return [
            'controller' => $entry['controller'],
            'method' => $method,
            'params' => ['id' => $id],
        ];
    }

    protected function matchByLength($id)
    {
        $entry = $this->config['by_length'][strlen($id)] ?? null;

        if (!$entry) {
            return null;
        }

        $actionCode = $this->segments[1] ?? null;
        $method = $this->resolveAction($entry['actions'] ?? [], $actionCode);

        return [
            'controller' => $entry['controller'],
            'method' => $method,
            'params' => ['id' => $id],
        ];
    }

    protected function matchPlace()
    {
        $entry = $this->config['place'];
        $codes = [];
        $actionCode = null;

        foreach ($this->segments as $segment) {
            if (ctype_alpha($segment)) {
                $codes[] = $segment;
            } elseif (ctype_digit($segment)) {
                $actionCode = $segment;
                break;
            } else {
                return null;
            }
        }

        return [
            'controller' => $entry['controller'],
            'method' => $this->resolveAction($entry['actions'] ?? [], $actionCode),
            'params' => ['codes' => $codes],
        ];
    }

    protected function resolveAction(array $actions, $code)
    {
        if ($code !== null && isset($actions[(int) $code])) {
            return $actions[(int) $code];
        }

        return 'show';
    }

    protected function callController($match)
    {
        $fullClass = 'App\\Controllers\\' . $match['controller'];

        if (!class_exists($fullClass)) {
            return $this->handleError("Controller not found: $fullClass");
        }

        $controller = new $fullClass();
        $method = $match['method'];

        if (!method_exists($controller, $method)) {
            return $this->handleError("Method not found: {$method}");
        }

        return call_user_func([$controller, $method], $match['params']);
    }

    protected function handleNotFound()
    {
        http_response_code(404);
        echo '<h1>404 - Pagina no encontrada</h1>';
    }

    protected function handleError($message)
    {
        http_response_code(500);
        echo '<h1>500 - ' . htmlspecialchars($message) . '</h1>';
    }
}
