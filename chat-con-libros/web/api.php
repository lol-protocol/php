<?php
declare(strict_types=1);

/*
 * Punto de entrada de la API de la página web (la lógica está en src/Api.php).
 * Se sirve con:  php -S 127.0.0.1:8080 -t web
 */

require __DIR__ . '/../src/cargar.php';

use ChatLibros\Api;

$cabeceras = array_change_key_case(function_exists('getallheaders') ? getallheaders() : [], CASE_LOWER);
$cuerpo = (string) file_get_contents('php://input', false, null, 0, Api::MAX_CUERPO + 1);

$respuesta = (new Api(dirname(__DIR__)))->manejar(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    is_string($_GET['accion'] ?? null) ? $_GET['accion'] : '',
    $cabeceras,
    $cuerpo,
);

http_response_code($respuesta['codigo']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
echo json_encode($respuesta['cuerpo'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
