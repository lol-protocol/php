<?php

declare(strict_types=1);

use App\Support\Container;
use App\Support\HealthCheck;
use App\Support\HostParser;
use App\Support\Router;
use App\Support\UrlHelper;
use App\Support\ServiceLocator;
use App\Support\Logger;
use App\Support\SessionManager;
use App\Support\HttpSecurityHeaders;
use App\Support\RateLimiter;

/**
 * Main Application Entry Point
 * Loads the route registry for the current domain and dispatches the request.
 */

require __DIR__ . '/vendor/autoload.php';

define('APP_BASE_PATH', '/');
define('DEBUG_MODE', getenv('DEBUG') === 'true');

HealthCheck::handle();

SessionManager::getInstance()->start();
HttpSecurityHeaders::setSecurityHeaders();

$container = Container::getInstance();

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$pos_domains = explode(',', getenv('POS_DOMAINS') ?: 'contrastocolor.local,contrastocolor.test,contrastocolor.app');
$pos_domains = array_map('trim', $pos_domains);
$site = HostParser::matchesDomain($host, $pos_domains) ? 'pos' : 'genealogy';
$locale = HostParser::getLocale($host);

$container->singleton('router', function ($c) use ($site) {
    $router = new Router();
    $router->loadConfig(__DIR__ . "/config/routes/{$site}.php");
    return $router;
});

$container->singleton('url', function ($c) {
    return new UrlHelper($c->get('router'));
});

ServiceLocator::initialize($container, $locale);
$locator = ServiceLocator::getInstance();

if (!RateLimiter::getInstance()->checkLimit(100, 60)) {
    echo '<h1>429 - Too Many Requests</h1>';
    exit;
}

$response = $locator->getRouter()->dispatch();
echo $response;

function view(string $name, array $data = []): string
{
    // Prevent path traversal attacks
    $safeName = preg_replace('/\.\./', '', $name);
    $file = __DIR__ . '/views/' . $safeName . '.php';

    if (!file_exists($file)) {
        $logger = ServiceLocator::getInstance()->getLogger();
        $logger->warning('View not found', ['view' => $name]);
        return "View not found: " . htmlspecialchars($name);
    }

    ob_start();
    try {
        (function() use ($file, $data): void {
            foreach ($data as $key => $value) {
                // $file and $data are this scope's own variables; letting a
                // data key overwrite $file would change which file gets included.
                if ($key === 'file' || $key === 'data') {
                    continue;
                }
                ${$key} = $value;
            }
            include $file;
        })();
        return (string)ob_get_clean();
    } catch (\Throwable $e) {
        ob_end_clean();
        ServiceLocator::getInstance()->getLogger()->error('View error', [
            'view' => $name,
            'error' => $e->getMessage(),
        ]);
        return '<h1>Error loading view</h1>';
    }
}

function enlace(string $tipo, int|string $id): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->enlace($tipo, $id);
}

function accion(string $tipo, int|string $id, int|string $codigo): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->accion($tipo, $id, $codigo);
}

function enlaceLugar(array $codes): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->enlaceLugar($codes);
}

function cuenta(int|string|null $codigo = null): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->cuenta($codigo);
}

function esc(mixed $text): string
{
    return ServiceLocator::getInstance()->getUrlHelper()->esc($text);
}

function log_info(string $message, array $context = []): void
{
    ServiceLocator::getInstance()->getLogger()->info($message, $context);
}

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
