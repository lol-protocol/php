<?php

declare(strict_types=1);

require __DIR__ . '/../codigo/ConexionBd.php';
require __DIR__ . '/../codigo/AlmacenDatos.php';
require __DIR__ . '/../codigo/AlmacenAcciones.php';
require __DIR__ . '/../codigo/AlmacenAlertas.php';
require __DIR__ . '/../codigo/AlmacenConfiguracion.php';
require __DIR__ . '/../codigo/AlmacenFiltros.php';
require __DIR__ . '/../codigo/AlmacenNotas.php';
require __DIR__ . '/../codigo/AlmacenKpis.php';
require __DIR__ . '/../codigo/AlmacenIntentosLogin.php';
require __DIR__ . '/../codigo/AlmacenAdministradores.php';
require __DIR__ . '/../codigo/ClienteEstadisticas.php';
require __DIR__ . '/../codigo/autenticacion.php';
require __DIR__ . '/../codigo/api.php';

// La interfaz (interfaz/) corre en otro puerto que esta API, así que la cookie de
// sesión viaja entre orígenes: hay que reflejar un origen conocido puntual (nunca
// "*") y habilitar credenciales explícitamente, o el navegador descarta la cookie.
// Configurable por variable de entorno (mismo patrón que ConexionBd.php), con
// default de desarrollo local para que el sistema funcione sin configurar nada.
$origenesPermitidos = [getenv('BACKOFFICE_CORS_ORIGEN') ?: 'http://localhost:8082'];
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
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

auth_iniciar_sesion_php();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$ruta = api_resolver_ruta($path);

// Libera el lock del archivo de sesión ni bien terminamos de leerla (auth +
// CSRF ya solo necesitan lectura de acá en más): si no, cualquier pedido
// concurrente de la misma pestaña -- aunque sea a otro endpoint -- se
// serializa esperando este mismo lock, sin importar cuántos workers tenga
// el server. Las rutas que manejan la sesión por su cuenta (login, logout,
// session; ver rutas.php) son la excepción: necesitan la sesión abierta para
// escribir en ella.
if ($ruta === null || !$ruta['sesion']) {
    session_write_close();
}

try {
    if ($ruta === null) {
        api_not_found();
    } elseif (!$ruta['sesion'] && !auth_esta_autenticado()) {
        api_unauthorized();
    } else {
        ($ruta['handler'])(...$ruta['args']);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error(500, 'error_interno', 'error interno del servidor');
}
