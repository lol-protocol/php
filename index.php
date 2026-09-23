<?php

/**
 * Main Application Entry Point
 * Handles routing for both genealogy and POS sites
 */

// Set environment variables
define('APP_BASE_PATH', '/');
define('DEBUG_MODE', getenv('DEBUG') === 'true');

// Autoloader
spl_autoload_register(function ($class) {
    $path = str_replace('\\', '/', $class);
    $file = __DIR__ . '/' . $path . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Start session
session_start();

// Initialize router
require 'Router.php';
$router = new Router();

// Determine which site is being accessed
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$site = determineSite($host);

// Register appropriate routes
if ($site === 'genealogy') {
    $router->registerRoutes('routes/genealogy.php');
} elseif ($site === 'pos') {
    $router->registerRoutes('routes/pos.php');
}

// Make router globally available for helper functions
$GLOBALS['router'] = $router;

// Dispatch the request
$response = $router->dispatch();

/**
 * Determine which site should be used based on domain
 */
function determineSite($host)
{
    // Remove port if present
    $host = explode(':', $host)[0];

    $genealogy_domains = [
        'genealogy.local',
        'genealogy.test',
        'genealogy.app',
        'localhost:8001',
    ];

    $pos_domains = [
        'contrastocolor.local',
        'contrastocolor.test',
        'contrastocolor.app',
        'pos.local',
        'localhost:8002',
    ];

    if (in_array($host, $genealogy_domains)) {
        return 'genealogy';
    }

    if (in_array($host, $pos_domains)) {
        return 'pos';
    }

    // Default to genealogy if not matched
    return 'genealogy';
}

/**
 * Simple templating function
 */
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
 * Redirect to a route
 */
function redirect($routeName, $params = [])
{
    global $router;
    $url = $router->route($routeName, $params);
    header('Location: ' . $url);
    exit;
}

/**
 * Get current site
 */
function currentSite()
{
    return determineSite($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/**
 * Security: Escape output
 */
function esc($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
