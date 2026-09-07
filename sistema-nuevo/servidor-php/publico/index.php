<?php

declare(strict_types=1);

require __DIR__ . '/../codigo/AlmacenDatos.php';
require __DIR__ . '/../codigo/AlmacenAcciones.php';
require __DIR__ . '/../codigo/AlmacenAlertas.php';
require __DIR__ . '/../codigo/AlmacenConfiguracion.php';
require __DIR__ . '/../codigo/AlmacenFiltros.php';
require __DIR__ . '/../codigo/ClienteEstadisticas.php';
require __DIR__ . '/../codigo/autenticacion.php';
require __DIR__ . '/../codigo/api.php';

// La interfaz (interfaz/) corre en otro puerto que esta API, así que la cookie de
// sesión viaja entre orígenes: hay que reflejar un origen conocido puntual (nunca
// "*") y habilitar credenciales explícitamente, o el navegador descarta la cookie.
$origenesPermitidos = ['http://localhost:8082'];
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origen, $origenesPermitidos, true)) {
    header('Access-Control-Allow-Origin: ' . $origen);
    header('Access-Control-Allow-Credentials: true');
}

// El login manda Content-Type: application/json, así que el navegador antepone
// un preflight OPTIONS: hay que responderlo (con los mismos headers CORS de
// arriba) antes de que llegue ninguna cookie de sesión real.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, DELETE');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

auth_iniciar_sesion_php();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$rutasProtegidas = ['/api/users', '/api/groups', '/api/action-types', '/api/alerts', '/api/alerts-config', '/api/filtros', '/api/timeline'];
$esFiltroPorId = (bool) preg_match('#^/api/filtros/\d+$#', $path);

try {
    if ((in_array($path, $rutasProtegidas, true) || $esFiltroPorId) && !auth_esta_autenticado()) {
        api_unauthorized();
    } elseif ($path === '/api/login') {
        api_login();
    } elseif ($path === '/api/logout') {
        api_logout();
    } elseif ($path === '/api/session') {
        api_session();
    } elseif ($path === '/api/users') {
        api_users();
    } elseif ($path === '/api/groups') {
        api_groups();
    } elseif ($path === '/api/action-types') {
        api_action_types();
    } elseif ($path === '/api/alerts') {
        api_alerts();
    } elseif ($path === '/api/alerts-config') {
        api_alertas_config();
    } elseif ($path === '/api/filtros') {
        api_filtros();
    } elseif (preg_match('#^/api/filtros/(\d+)$#', $path, $m)) {
        api_filtros_delete((int)$m[1]);
    } elseif ($path === '/api/timeline') {
        api_timeline();
    } else {
        api_not_found();
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
