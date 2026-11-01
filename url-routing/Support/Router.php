<?php

declare(strict_types=1);

namespace App\Support;

use App\Routing\ArchivoMatchStrategy;
use App\Routing\ReservedMatchStrategy;
use App\Routing\ByLengthMatchStrategy;
use App\Routing\PlaceMatchStrategy;
use App\Routing\ConfigValidator;
use App\Routing\MatchStrategy;

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

    /** @var array<string, mixed> */
    protected $config = [];

    /** @var list<string> */
    protected $segments = [];

    /** @var list<MatchStrategy> */
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

    /**
     * @return list<string>
     */
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

    /**
     * @return array{controller: string, method: string, params: array<string, mixed>}|null
     */
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

    /**
     * @param array{controller: string, method: string, params: array<string, mixed>} $match
     */
    protected function callController(array $match): string
    {
        $fullClass = 'App\\Controllers\\' . $match['controller'];

        if (!class_exists($fullClass)) {
            return $this->handleError("Controller not found: $fullClass");
        }

        try {
            // A page withdrawn after a pedido de retiro, with all its actions: see Retiros.
            if (($this->config['retiros'] ?? false) && isset($match['params']['id'])) {
                $id = (string)$match['params']['id'];
                $retirada = Retiros::bloqueo($this->typeForLength(strlen($id)), (int)$id);
                if ($retirada !== null) {
                    return $retirada;
                }
            }

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
        $pista = $this->pista();

        return '<h1>404 - Página no encontrada</h1>' . ($pista === null ? '' : "\n<p>{$pista}</p>");
    }

    /**
     * Why this path matches nothing, read from its shape and the route config
     * alone (HTML, already escaped). It never looks at the database: a hint
     * must not tell a missing id from a private one.
     */
    private function pista(): ?string
    {
        $primero = $this->segments[0] ?? '';
        $porLargo = $this->config['by_length'] ?? [];

        if (ctype_digit($primero)) {
            $entrada = $porLargo[strlen($primero)] ?? null;
            if ($entrada === null) {
                return $this->pistaDeLargo($primero, $porLargo);
            }

            $acciones = $entrada['actions'] ?? [];
            $lista = implode(', ', array_map(
                static fn(int $codigo, string $metodo): string => "{$codigo} {$metodo}",
                array_keys($acciones),
                $acciones
            ));
            $tipo = htmlspecialchars($entrada['type']);

            return count($this->segments) > 2 || $acciones === []
                ? "Una URL de {$tipo} admite " . ($acciones === [] ? 'solo el id, sin acciones.' : "una sola acción: {$lista}.")
                : "{$tipo} no tiene esa acción. Sus acciones: {$lista}.";
        }

        return match (true) {
            isset($this->config['place']) && preg_match('/^[a-z]{2}$/', $primero) === 1
                => 'Un lugar es país (2 letras), región y ciudad (1-3 letras cada una), y como mucho una acción: /mx/jal/gdl/1/.',
            preg_match('/^[a-z]{3}$/', $primero) === 1
                => 'Un código de 3 letras es un idioma, y el idioma va en el subdominio (eng.…, spa.…), no en la ruta.',
            preg_match('/^[a-z]{4,}$/', $primero) === 1
                => 'Las URLs de este sitio no llevan palabras. Para buscar, usa el buscador.',
            preg_match('/^[a-z]$/', $primero) === 1 => 'No existe esa función.',
            default => null,
        };
    }

    /**
     * An id whose digit count no type uses: say so, and which padded ids it could have meant.
     *
     * @param array<int, array<string, mixed>> $porLargo
     */
    private function pistaDeLargo(string $id, array $porLargo): string
    {
        $largo = strlen($id);
        $opciones = [];
        foreach ($porLargo as $ancho => $entrada) {
            // A random code (an order's) padded from a guess is never the right one: don't suggest it.
            if ($ancho > $largo && ($entrada['sugerir'] ?? true)) {
                $url = '/' . str_pad($id, $ancho, '0', STR_PAD_LEFT) . '/';
                $opciones[] = '<a href="' . $url . '">' . $url . '</a> (' . htmlspecialchars($entrada['type']) . ')';
            }
        }

        $texto = "Ningún tipo usa {$largo} dígitos: cada tipo tiene un ancho fijo, con ceros a la izquierda.";
        return $opciones === [] ? $texto : $texto . ' ¿Quisiste decir ' . implode(', ', $opciones) . '?';
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
