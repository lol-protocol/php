<?php

declare(strict_types=1);

use App\Support\AccessPolicy;
use App\Support\Config;
use App\Support\Container;
use App\Support\Database;
use App\Support\ErrorPage;
use App\Support\Format;
use App\Support\HealthCheck;
use App\Support\HostParser;
use App\Support\Migrator;
use App\Support\Router;
use App\Support\UrlHelper;
use App\Support\ServiceLocator;
use App\Support\Logger;
use App\Support\SessionManager;
use App\Support\Textos;
use App\Support\HttpSecurityHeaders;
use App\Support\RateLimiter;
use App\Support\RegistroDeUrls;
use App\Support\ResumenDeError;
use App\Support\Vista;

/**
 * Main Application Entry Point
 * Loads the route registry for the current domain and dispatches the request.
 */

// PHP's built-in server (development and the tests) hands EVERY request to this script when it is the router,
// files included; a real web server (nginx, Apache) serves an existing file itself and never gets here. So that
// /b.js works in both, let the built-in server serve a file that exists under public/ (never a dotfile or a .php).
if (PHP_SAPI === 'cli-server') {
    $estatico = realpath(__DIR__ . '/' . ltrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/'));
    if ($estatico !== false && is_file($estatico) && str_starts_with($estatico, __DIR__ . DIRECTORY_SEPARATOR)
        && !str_ends_with($estatico, '.php') && !str_starts_with(basename($estatico), '.')) {
        return false;
    }
}

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/vendor/autoload.php';

Config::load();

define('APP_BASE_PATH', '/');
define('DEBUG_MODE', getenv('DEBUG') === 'true');

HealthCheck::handle();

SessionManager::getInstance()->resume();
HttpSecurityHeaders::setSecurityHeaders();

$container = Container::getInstance();

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$site = HostParser::sitio($host);
$locale = HostParser::getLocale($host);

$container->singleton('router', function ($c) use ($site) {
    $router = new Router();
    $router->loadConfig(APP_ROOT . "/config/routes/{$site}.php");
    return $router;
});

// Each site has its own database; see docs/DATABASE.md for which engine
// fits which site. The connection opens lazily on the first query.
$container->singleton('db', fn() => Database::forSite($site));

$container->singleton('url', function ($c) {
    return new UrlHelper($c->get('router'));
});

ServiceLocator::initialize($container, $locale);
$locator = ServiceLocator::getInstance();

if (!RateLimiter::getInstance()->checkLimit(100, 60)) {
    echo ErrorPage::body(429, 'Demasiadas solicitudes');
    exit;
}

// After the limiter, because this one does reach the database.
HealthCheck::handleReady($container->get('db'), Migrator::forSite($container->get('db'), $site), $locator->getLogger());

$response = $locator->getRouter()->dispatch();
registrar_url($locator->getRouter());
echo $response;

/**
 * Counts a 404 or a 301 by the shape of its URL (see RegistroDeUrls: never the path, the query or who asked).
 * Whatever goes wrong here must not reach the visitor.
 */
function registrar_url(Router $router): void
{
    try {
        $estado = http_response_code() ?: 200;
        if ($estado !== 301 && $estado !== 404) {
            return;
        }

        $destino = null;
        foreach (headers_list() as $cabecera) {
            if (stripos($cabecera, 'Location:') === 0) {
                $destino = trim(substr($cabecera, 9));
            }
        }
        $motivo = RegistroDeUrls::desdeConfig()->registrar($estado, $_SERVER['REQUEST_URI'] ?? '/', $destino, $router->palabras());
        if ($motivo !== null) {
            registrar_url_fallo($motivo);
        }
    } catch (\Throwable $e) {
        // A counter that fails is not worth a failed page, but whoever runs the site should know it stopped.
        registrar_url_fallo($e::class);
    }
}

/** Says in the log that the URL counter is not working: once per PHP process, not once per 404. */
function registrar_url_fallo(string $motivo): void
{
    static $avisado = false;
    if ($avisado) {
        return;
    }
    $avisado = true;
    try {
        ServiceLocator::getInstance()->getLogger()->warning('El registro de URLs (URL_LOG_DIR) no está funcionando', ['motivo' => $motivo]);
    } catch (\Throwable) {
        // The log itself is what failed: nothing left to tell.
    }
}

/**
 * @param array<string, mixed> $data
 */
function view(string $name, array $data = []): string
{
    // Prevent path traversal attacks
    $safeName = preg_replace('/\.\./', '', $name);
    $file = APP_ROOT . '/views/' . $safeName . '.php';

    // A page that cannot be drawn is a 500, not a 200 with a line of text: a crawler would index it and the
    // monitoring would see nothing wrong.
    if (!file_exists($file)) {
        ServiceLocator::getInstance()->getLogger()->warning('View not found', ['view' => $name]);
        http_response_code(500);

        return ErrorPage::page(500, Textos::t('error.500'));
    }

    try {
        return Vista::dibujar($file, $data);
    } catch (\Throwable $e) {
        ServiceLocator::getInstance()->getLogger()->error('View error', [
            'view' => $name,
            'error' => ResumenDeError::de($e),
        ]);
        http_response_code(500);

        return ErrorPage::page(500, Textos::t('error.500'));
    }
}

function enlace(string $tipo, int|string $id): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->enlace($tipo, $id);
}

/**
 * @param array<string, int|string> $filtros type => id
 */
function filtrado(string $tipo, int|string $id, array $filtros): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->filtrado($tipo, $id, $filtros);
}

function accion(string $tipo, int|string $id, int|string $codigo): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->accion($tipo, $id, $codigo);
}

/**
 * @param list<string> $codes
 */
function enlaceLugar(array $codes): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->enlaceLugar($codes);
}

/**
 * @param list<string> $codes
 */
function accionLugar(array $codes, int|string $codigo): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->accionLugar($codes, $codigo);
}

function busqueda(int $digitos, string $texto = '', int $pagina = 1): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->busqueda($digitos, $texto, $pagina);
}

function cuenta(int|string|null $codigo = null): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->cuenta($codigo);
}

/** Whether this request is the owner's: pages use it to show controls only she can use. */
function es_propietario(): bool
{
    return AccessPolicy::esPropietario();
}

function esc(mixed $text): string
{
    return UrlHelper::escape($text);
}

/**
 * A text in the language of the subdomain (lang/{código}.php; Spanish when it is missing). Plain: esc() it where it is shown.
 *
 * @param array<string, string|int> $datos
 */
function t(string $clave, array $datos = []): string
{
    return Textos::t($clave, $datos);
}

/**
 * @param array<string, mixed> $context
 */
function log_info(string $message, array $context = []): void
{
    ServiceLocator::getInstance()->getLogger()->info($message, $context);
}

/**
 * @param array<string, mixed> $context
 */
function log_error(string $message, array $context = []): void
{
    ServiceLocator::getInstance()->getLogger()->error($message, $context);
}

function get_locale(): string
{
    return ServiceLocator::getInstance()->getLocale();
}

function get_csrf_token(): string
{
    return ServiceLocator::getInstance()->getSessionManager()->setCsrfToken();
}

function fecha(?string $valor): string
{
    return Format::fecha($valor, get_locale());
}

function vida(?string $nacimiento, ?string $defuncion): string
{
    return Format::vida($nacimiento, $defuncion);
}

function dinero(int|string|null $centavos, string $moneda = 'MXN'): string
{
    return Format::dinero($centavos, $moneda);
}
