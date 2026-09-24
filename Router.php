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
 * the matched resource. An action segment that doesn't map to
 * anything is a 404, not a silent fall-back to the base resource —
 * and so is any segment beyond what a type's shape allows (extra
 * trailing garbage, a 4th place level). Nothing is silently ignored.
 *
 * Contract: numeric ids MUST be generated zero-padded to their
 * type's fixed digit width (see `enlace()` in index.php) — an
 * unpadded id silently resolves as a different, shorter type.
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

        if ($path === '') {
            return [];
        }

        // Letters are canonicalized to lowercase so /MX/ and /mx/ are
        // the same URL; digits are untouched (case has no meaning there).
        return array_map('strtolower', explode('/', $path));
    }

    public function loadConfig($configFile)
    {
        $this->config = require $configFile;
    }

    /**
     * Digit width reserved for a given type name, or null if it isn't
     * a by_length type (e.g. "lugar" or "order", which aren't).
     */
    public function typeLength($type)
    {
        foreach ($this->config['by_length'] ?? [] as $length => $entry) {
            if ($entry['type'] === $type) {
                return $length;
            }
        }

        return null;
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

        if (isset($this->config['reserved'][$first]) && count($this->segments) <= 2) {
            return $this->matchReserved($first);
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

    protected function matchReserved($first)
    {
        $entry = $this->config['reserved'][$first];
        $actionCode = $this->segments[1] ?? null;
        $method = $this->resolveAction($entry['actions'] ?? [], $actionCode, $entry['method'] ?? 'index');

        if ($method === null) {
            return null;
        }

        return [
            'controller' => $entry['controller'],
            'method' => $method,
            'params' => [],
        ];
    }

    protected function matchOrder()
    {
        if (count($this->segments) > 3) {
            return null;
        }

        $id = $this->segments[1] ?? null;

        if (!$id || !ctype_digit($id)) {
            return null;
        }

        $entry = $this->config['order'];
        $actionCode = $this->segments[2] ?? null;
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

    protected function matchByLength($id)
    {
        if (count($this->segments) > 2) {
            return null;
        }

        $entry = $this->config['by_length'][strlen($id)] ?? null;

        if (!$entry) {
            return null;
        }

        $actionCode = $this->segments[1] ?? null;
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

    /**
     * Place hierarchy is at most 3 levels (pais/region/ciudad), optionally
     * followed by one numeric action segment. Anything beyond that shape
     * — a 4th place level, junk after the action — is a 404, not a
     * silent truncation to whatever came first.
     */
    protected function matchPlace()
    {
        $entry = $this->config['place'];
        $codes = $this->segments;
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

    /**
     * null code -> base resource ("show" or the entry's own default).
     * Unmapped code -> null, meaning "404", never a silent fall-back.
     */
    protected function resolveAction(array $actions, $code, $default = 'show')
    {
        if ($code === null) {
            return $default;
        }

        return $actions[(int) $code] ?? null;
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
