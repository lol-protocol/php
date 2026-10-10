<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuditoriaController;
use App\Controllers\ClientesController;
use App\Controllers\BoletasController;
use App\Controllers\CohortesController;
use App\Controllers\DashboardController;
use App\Controllers\FunnelController;
use App\Controllers\PagosController;
use App\Database;
use App\ManejadorDeErrores;
use App\Peticion;
use App\Router;
use App\CabecerasDeSeguridad;

ini_set('display_errors', '0');
ManejadorDeErrores::registrar();
Peticion::normalizarParametros();

foreach (CabecerasDeSeguridad::listado() as $nombre => $valor) {
    header("{$nombre}: {$valor}");
}

try {
    Database::connection();
} catch (Throwable $e) {
    // El detalle (que variable falta, o por que fallo la conexion) va al log
    // y no a la respuesta: puede nombrar el host o el usuario de la base.
    error_log('No se pudo conectar a la base de datos: ' . $e->getMessage());
    http_response_code(500);
    echo 'No se pudo conectar a la base de datos. Revisá la configuración (variables DB_*, ver README) y el log de errores de PHP.';
    exit;
}

$router = new Router();

// Sin login: todas las paginas son publicas (ver _Garbage/README.md).
$paginas = [
    'dashboard' => fn () => (new DashboardController())->index(),
    'cobros' => fn () => (new BoletasController())->index(),
    'boleta-nueva' => fn () => (new BoletasController())->nueva(),
    'boleta-editar' => fn () => (new BoletasController())->editar(),
    'boleta-anular' => fn () => (new BoletasController())->anular(),
    'pagos' => fn () => (new PagosController())->index(),
    'pago-nuevo' => fn () => (new PagosController())->nuevo(),
    'pago-editar' => fn () => (new PagosController())->editar(),
    'pago-anular' => fn () => (new PagosController())->anular(),
    'funnel' => fn () => (new FunnelController())->index(),
    'cohortes' => fn () => (new CohortesController())->index(),
    'clientes' => fn () => (new ClientesController())->index(),
    'cliente-nuevo' => fn () => (new ClientesController())->nuevo(),
    'cliente' => fn () => (new ClientesController())->ficha(),
    'auditoria' => fn () => (new AuditoriaController())->index(),
];
foreach ($paginas as $pagina => $manejador) {
    $router->add($pagina, $manejador);
}

$page = $_GET['page'] ?? 'dashboard';
$router->dispatch($page);
