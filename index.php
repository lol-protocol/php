<?php

/**
 * Main Application Entry Point
 * Loads the route registry for the current domain and dispatches the request.
 */

define('APP_BASE_PATH', '/');
define('DEBUG_MODE', getenv('DEBUG') === 'true');

spl_autoload_register(function ($class) {
    $relative = preg_replace('/^App\\\\/', '', $class);
    $path = str_replace('\\', '/', $relative);
    $file = __DIR__ . '/' . $path . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

session_start();

require 'Router.php';
$router = new Router();

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$site = determineSite($host);
$GLOBALS['locale'] = determineLocale($host);

$router->loadConfig(__DIR__ . "/routes/{$site}.php");
$GLOBALS['router'] = $router;

$response = $router->dispatch();

/**
 * Which route registry to load, based on domain.
 *
 * Matches the domain itself or any of its subdomains — never a bare
 * string suffix, or "evilcontrastocolor.local" would match
 * "contrastocolor.local" too (same trailing characters, different host).
 */
function determineSite($host)
{
    $host = explode(':', $host)[0];

    $pos_domains = [
        'contrastocolor.local',
        'contrastocolor.test',
        'contrastocolor.app',
    ];

    foreach ($pos_domains as $domain) {
        if ($host === $domain || str_ends_with($host, '.' . $domain)) {
            return 'pos';
        }
    }

    return 'genealogy';
}

/**
 * Language subdomain, ISO 639-2 (3 letters): spa, eng, ...
 * Defaults to spa when no subdomain matches a supported language —
 * an unrelated 3-letter subdomain (api, cdn, ...) must not be read as one.
 */
function determineLocale($host)
{
    $idiomas_soportados = ['spa', 'eng'];

    $parts = explode('.', explode(':', $host)[0]);
    $sub = strtolower($parts[0] ?? '');

    if (count($parts) > 2 && in_array($sub, $idiomas_soportados, true)) {
        return $sub;
    }

    return 'spa';
}

function view($name, $data = [])
{
    $file = __DIR__ . '/views/' . $name . '.php';

    if (!file_exists($file)) {
        return "View not found: $name";
    }

    extract($data);
    ob_start();
    include $file;
    return ob_get_clean();
}

/**
 * URL for a numeric-id resource. $id is zero-padded to $tipo's fixed
 * digit width before it's used — an unpadded id would silently resolve
 * as a different (shorter) type, so callers never hand-format this.
 */
function enlace($tipo, $id)
{
    if (!ctype_digit((string) $id)) {
        throw new InvalidArgumentException("Id no numerico para {$tipo}: {$id}");
    }

    $largo = $GLOBALS['router']->typeLength($tipo);

    if ($largo === null) {
        throw new InvalidArgumentException("Tipo desconocido: {$tipo}");
    }

    if (strlen((string) $id) > $largo) {
        throw new InvalidArgumentException("Id demasiado largo para {$tipo} ({$largo} digitos): {$id}");
    }

    return '/' . str_pad((string) $id, $largo, '0', STR_PAD_LEFT) . '/';
}

/**
 * URL for a sub-action on a numeric-id resource (see enlace()).
 */
function accion($tipo, $id, $codigo)
{
    return rtrim(enlace($tipo, $id), '/') . '/' . $codigo . '/';
}

/**
 * URL for a place, from its list of hierarchical text codes (pais/region/ciudad).
 * Mirrors the router's own shape check (1-3 alphabetic codes) so a bad
 * call fails here, at generation time, instead of producing a link that
 * 404s when someone clicks it.
 */
function enlaceLugar(array $codes)
{
    if (empty($codes) || count($codes) > 3) {
        throw new InvalidArgumentException('Un lugar tiene entre 1 y 3 codigos');
    }

    foreach ($codes as $code) {
        if (!ctype_alpha($code)) {
            throw new InvalidArgumentException("Codigo de lugar invalido: {$code}");
        }
    }

    return '/' . implode('/', array_map('strtolower', $codes)) . '/';
}

/**
 * URL for the account area, optionally a sub-section by its action code.
 */
function cuenta($codigo = null)
{
    return $codigo === null ? '/0/' : "/0/{$codigo}/";
}

function esc($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
