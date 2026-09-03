<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\CobrosController;
use App\Controllers\DashboardController;
use App\Controllers\FunnelController;
use App\Controllers\PagosController;
use App\Router;

$dbFile = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dbFile)) {
    http_response_code(500);
    echo 'La base de datos no existe todavia. Corre: php database/seed.php';
    exit;
}

$router = new Router();
$router->add('dashboard', fn () => (new DashboardController())->index());
$router->add('cobros', fn () => (new CobrosController())->index());
$router->add('pagos', fn () => (new PagosController())->index());
$router->add('funnel', fn () => (new FunnelController())->index());

$page = $_GET['page'] ?? 'dashboard';
$router->dispatch($page);
