<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/api/ayudantes.php';

// Todo error de la API sale por api_error(): el texto en español (`error`, para quien lee la respuesta a mano)
// MÁS un `codigo` estable, que la interfaz traduce al idioma elegido (claves err_<codigo>; ver
// pruebas/js/errores.test.mjs). Antes la interfaz mostraba el `error` en español tal cual, también en inglés.
$respuesta = function (callable $emitir): array {
    ob_start();
    $emitir();
    return ['estado' => http_response_code(), 'crudo' => ob_get_clean()];
};

http_response_code(200);
$r = $respuesta(fn () => api_error(404, 'filtro_no_encontrado', 'filtro no encontrado'));
assert_igual(404, $r['estado'], 'api_error: fija el código HTTP');
assert_igual(
    ['error' => 'filtro no encontrado', 'codigo' => 'filtro_no_encontrado'],
    json_decode($r['crudo'], true),
    'api_error: el cuerpo lleva el texto (error) y el identificador estable (codigo)'
);

$r = $respuesta(fn () => api_error(429, 'demasiados_intentos', 'demasiados intentos fallidos, probá de nuevo más tarde', ['retry_after' => 1730000000]));
$cuerpo = json_decode($r['crudo'], true);
assert_igual(429, $r['estado'], 'api_error: también con datos propios del error (429)');
assert_igual(1730000000, $cuerpo['retry_after'] ?? null, 'api_error: los datos propios (retry_after) viajan junto al error');
assert_igual('demasiados_intentos', $cuerpo['codigo'] ?? null, 'api_error: el codigo no se pierde por sumar datos propios');
$aEscapada = substr(json_encode('á'), 1, -1); // la tilde como la escribe json_encode() por defecto: barra, u, 00e1
assert_verdadero(str_contains($r['crudo'], 'probá') && !str_contains($r['crudo'], $aEscapada), 'api_error: UTF-8 crudo, no escapado');

$r = $respuesta(fn () => api_not_found());
assert_igual([404, 'ruta_no_encontrada'], [$r['estado'], json_decode($r['crudo'], true)['codigo'] ?? null], 'api_not_found: 404 con su codigo');
$r = $respuesta(fn () => api_unauthorized());
assert_igual([401, 'no_autenticado'], [$r['estado'], json_decode($r['crudo'], true)['codigo'] ?? null], 'api_unauthorized: 401 con su codigo');

// Que ningún endpoint arme su error a mano (sin codigo): ni un 'error' => suelto ni un http_response_code(4xx/5xx)
// fuera de api_error(). Si alguien lo agrega, la interfaz volvería a mostrar ese error solo en español.
$sueltos = archivos_con_patron(
    fuentes_php(realpath(__DIR__ . '/../../servidor-php')),
    '/[\'"]error[\'"]\s*=>|http_response_code\(\s*[45]\d\d/',
    ['api_error'] // el cuerpo de api_error() es el único lugar donde se arma la respuesta de error
);
assert_igual([], $sueltos, 'api: toda respuesta de error sale por api_error() (con su codigo), ninguna armada a mano');
