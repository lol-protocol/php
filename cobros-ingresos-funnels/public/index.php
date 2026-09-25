<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth;
use App\Controllers\AuditoriaController;
use App\Controllers\ClienteController;
use App\Controllers\CobrosController;
use App\Controllers\CohortesController;
use App\Controllers\DashboardController;
use App\Controllers\FunnelController;
use App\Controllers\LoginController;
use App\Controllers\PagosController;
use App\Controllers\UsuarioController;
use App\Database;
use App\ErrorHandler;
use App\Peticion;
use App\Router;
use App\SecurityHeaders;

ini_set('display_errors', '0');
ErrorHandler::registrar();
Peticion::normalizarParametros();

foreach (SecurityHeaders::listado() as $nombre => $valor) {
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

Auth::iniciar();

$router = new Router();
$router->add('login', fn () => (new LoginController())->index());
$router->add('logout', fn () => (new LoginController())->salir());

$paginasProtegidas = [
    'dashboard' => fn () => (new DashboardController())->index(),
    'cobros' => fn () => (new CobrosController())->index(),
    'boleta-nueva' => fn () => (new CobrosController())->nueva(),
    'boleta-editar' => fn () => (new CobrosController())->editar(),
    'boleta-anular' => fn () => (new CobrosController())->anular(),
    'pagos' => fn () => (new PagosController())->index(),
    'pago-nuevo' => fn () => (new PagosController())->nuevo(),
    'pago-editar' => fn () => (new PagosController())->editar(),
    'pago-anular' => fn () => (new PagosController())->anular(),
    'funnel' => fn () => (new FunnelController())->index(),
    'cohortes' => fn () => (new CohortesController())->index(),
    'clientes' => fn () => (new ClienteController())->index(),
    'cliente-nuevo' => fn () => (new ClienteController())->nuevo(),
    'cliente' => fn () => (new ClienteController())->ficha(),
    'auditoria' => fn () => (new AuditoriaController())->index(),
    'usuarios' => fn () => (new UsuarioController())->index(),
    'usuario-nuevo' => fn () => (new UsuarioController())->nuevo(),
    'usuario-password' => fn () => (new UsuarioController())->cambiarPassword(),
    'usuario-revocar' => fn () => (new UsuarioController())->revocar(),
];
foreach ($paginasProtegidas as $pagina => $manejador) {
    $router->add($pagina, function () use ($manejador) {
        Auth::requerir();
        $manejador();
    });
}

$page = $_GET['page'] ?? 'dashboard';
$router->dispatch($page);
