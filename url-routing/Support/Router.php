<?php

declare(strict_types=1);

namespace App\Support;

use App\Routing\ArchivoMatchStrategy;
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
 *   - exact match -> a reserved system path (/0/, POS's /c/ and /p/)
 *   - a file name -> robots.txt, sitemaps (see `archivos`)
 *
 * Every page has one canonical URL: lowercase, single slashes, ending
 * in "/" (a file name ends in its extension instead). A GET for any
 * other spelling is a 301 to it, and so is a path listed in a site's
 * `redirect` map (URLs that moved).
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
    /** First path segment reserved for the account area on every site. */
    public const ACCOUNT_SEGMENT = '0';

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
            new ArchivoMatchStrategy(),       // File names (/robots.txt): before the others, a dot fits no other shape
            new ByLengthMatchStrategy(),      // Most common (numeric IDs: /1234/)
            new PlaceMatchStrategy(),          // Common (places: /mx/jalisco/)
            new ReservedMatchStrategy(),       // System paths (/0/, /c/)
        ];
    }

    /**
     * The canonical spelling of $uri (query string kept), or null when $uri
     * already is canonical, or is something no redirect should point at.
     * Only plain URL characters get a redirect: a "\" in a Location can read
     * as "//" to a browser, which would send it to another host.
     */
    public static function canonica(string $uri): ?string
    {
        $corte = strpos($uri, '?');
        $ruta = $corte === false ? $uri : substr($uri, 0, $corte);
        $query = $corte === false ? '' : substr($uri, $corte);

        $segmentos = array_values(array_filter(explode('/', $ruta), static fn(string $s): bool => $s !== ''));
        foreach ($segmentos as $segmento) {
            if (preg_match('/^[A-Za-z0-9._~%-]+$/', $segmento) !== 1) {
                return null;
            }
        }

        $canonica = '/' . strtolower(implode('/', $segmentos));
        if ($segmentos !== [] && !str_contains(end($segmentos), '.')) {
            $canonica .= '/';
        }

        return $canonica === $ruta ? null : $canonica . ($query === '?' ? '' : $query);
    }

    /** Where this request's path moved to, per the site's `redirect` map; null when it didn't. */
    private function movida(): ?string
    {
        $destino = $this->config['redirect'][implode('/', $this->segments)] ?? null;
        if ($destino === null) {
            return null;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $corte = strpos($uri, '?');
        return $destino . ($corte === false ? '' : substr($uri, $corte));
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

    /** Reverse of typeLength(): the type a digit count selects, if any. */
    public function typeForLength(int $length): string|null
    {
        return $this->config['by_length'][$length]['type'] ?? null;
    }

    public function dispatch(): string
    {
        // Only GET/HEAD: a 301 turns a POST into a GET and would drop its body.
        if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
            $destino = $this->movida() ?? self::canonica($_SERVER['REQUEST_URI'] ?? '/');
            if ($destino !== null) {
                http_response_code(301);
                header("Location: {$destino}");
                return '';
            }
        }

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
            return $this->handleError(
                "Controller error in {$fullClass}::{$match['method']}: " . $e::class . ': ' . $e->getMessage()
                    . ' at ' . $e->getFile() . ':' . $e->getLine()
            );
        }
    }

    protected function handleNotFound(): string
    {
        http_response_code(404);
        return '<h1>404 - Pagina no encontrada</h1>';
    }

    /**
     * The detail can carry SQL, class names or connection info: it goes to
     * the log, and only reaches the response when DEBUG is on.
     */
    protected function handleError(string $detail): string
    {
        Logger::getInstance()->error('Request failed', ['detail' => $detail]);
        http_response_code(500);

        $debug = defined('DEBUG_MODE') && DEBUG_MODE;
        return '<h1>500 - ' . htmlspecialchars($debug ? $detail : 'Error interno del servidor') . '</h1>';
    }
}
