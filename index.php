<?php

declare(strict_types=1);

use App\Support\ClassLoader;
use App\Support\Container;
use App\Support\HostParser;
use App\Support\UrlHelper;

/**
 * Main Application Entry Point
 * Loads the route registry for the current domain and dispatches the request.
 */

define('APP_BASE_PATH', '/');
define('DEBUG_MODE', getenv('DEBUG') === 'true');

ClassLoader::register();
session_start();

require 'Router.php';

$container = Container::getInstance();

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$pos_domains = ['contrastocolor.local', 'contrastocolor.test', 'contrastocolor.app'];
$site = HostParser::matchesDomain($host, $pos_domains) ? 'pos' : 'genealogy';
$locale = HostParser::getLocale($host);

$container->singleton('router', function ($c) use ($site) {
    $router = new Router();
    $router->loadConfig(__DIR__ . "/routes/{$site}.php");
    return $router;
});

$container->singleton('url', function ($c) {
    return new UrlHelper($c->get('router'));
});

$router = $container->get('router');
$urlHelper = $container->get('url');

$GLOBALS['locale'] = $locale;
$GLOBALS['router'] = $router;
$GLOBALS['url'] = $urlHelper;

$response = $router->dispatch();
echo $response;

function view($name, $data = [])
{
    $file = __DIR__ . '/views/' . $name . '.php';

    if (!file_exists($file)) {
        return "View not found: $name";
    }

    ob_start();
    (function() use ($file, $data) {
        foreach ($data as $key => $value) {
            ${$key} = $value;
        }
        include $file;
    })();
    return ob_get_clean();
}

function enlace($tipo, $id)
{
    return $GLOBALS['url']->enlace($tipo, $id);
}

function accion($tipo, $id, $codigo)
{
    return $GLOBALS['url']->accion($tipo, $id, $codigo);
}

function enlaceLugar(array $codes)
{
    return $GLOBALS['url']->enlaceLugar($codes);
}

function cuenta($codigo = null)
{
    return $GLOBALS['url']->cuenta($codigo);
}

function esc($text)
{
    return $GLOBALS['url']->esc($text);
}
