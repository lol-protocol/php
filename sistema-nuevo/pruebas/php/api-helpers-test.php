<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/autenticacion.php';
require_once __DIR__ . '/../../servidor-php/codigo/api/ayudantes.php';

// Los helpers que todos los endpoints comparten: cómo se serializa una respuesta, cómo se lee el cuerpo, y las
// dos exigencias de los endpoints que cambian estado (método y token CSRF). Cada uno tenía su copia en 4 o 9
// archivos; la bandera de json_encode, en particular, fue una inconsistencia real entre endpoints.
$ejecutar = function (callable $f): array {
    http_response_code(200);
    ob_start();
    $resultado = $f();
    return ['resultado' => $resultado, 'estado' => http_response_code(), 'salida' => (string) ob_get_clean()];
};

// api_responder: UTF-8 crudo, no escapado.
$r = $ejecutar(fn () => api_responder(['nombre' => 'probá', 'n' => 1]));
assert_igual('{"nombre":"probá","n":1}', $r['salida'], 'api_responder: serializa a JSON con el UTF-8 crudo');

// api_cuerpo_json: siempre un array, venga lo que venga.
assert_igual(['a' => 1], api_cuerpo_json('{"a":1}'), 'cuerpo: un objeto JSON se lee como array');
assert_igual([], api_cuerpo_json(''), 'cuerpo: un pedido sin cuerpo es []');
assert_igual([], api_cuerpo_json('{}'), 'cuerpo: {} es []');
assert_igual([], api_cuerpo_json('esto no es json'), 'cuerpo: un JSON mal formado es [], no un error');
assert_igual([], api_cuerpo_json('123'), 'cuerpo: un JSON que no es objeto (número) es []');
assert_igual([], api_cuerpo_json('"texto"'), 'cuerpo: un JSON que no es objeto (string) es []');
assert_igual([], api_cuerpo_json('null'), 'cuerpo: null es []');
assert_igual(['x' => ['y' => 2]], api_cuerpo_json('{"x":{"y":2}}'), 'cuerpo: respeta objetos anidados');

// api_exigir_metodo: deja pasar el método pedido y responde 405 con el resto.
$_SERVER['REQUEST_METHOD'] = 'POST';
$r = $ejecutar(fn () => api_exigir_metodo('POST'));
assert_igual([true, 200, ''], [$r['resultado'], $r['estado'], $r['salida']], 'método: el método esperado pasa sin responder nada');
$r = $ejecutar(fn () => api_exigir_metodo('DELETE'));
assert_igual([false, 405], [$r['resultado'], $r['estado']], 'método: otro método responde 405 y avisa que hay que cortar');
assert_igual('metodo_no_permitido', json_decode($r['salida'], true)['codigo'] ?? null, 'método: el 405 lleva su codigo');
unset($_SERVER['REQUEST_METHOD']);

// api_exigir_csrf: el token del header tiene que ser el de la sesión.
$_SESSION = ['csrf_token' => 'token-de-la-sesion'];
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'token-de-la-sesion';
$r = $ejecutar(fn () => api_exigir_csrf());
assert_igual([true, 200, ''], [$r['resultado'], $r['estado'], $r['salida']], 'csrf: el token de la sesión pasa sin responder nada');
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'otro-token';
$r = $ejecutar(fn () => api_exigir_csrf());
assert_igual([false, 403], [$r['resultado'], $r['estado']], 'csrf: un token distinto responde 403 y avisa que hay que cortar');
assert_igual('csrf_invalido', json_decode($r['salida'], true)['codigo'] ?? null, 'csrf: el 403 lleva su codigo');
unset($_SERVER['HTTP_X_CSRF_TOKEN']);
$r = $ejecutar(fn () => api_exigir_csrf());
assert_igual([false, 403], [$r['resultado'], $r['estado']], 'csrf: sin el header también es 403');
unset($_SESSION);
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'cualquiera';
$r = $ejecutar(fn () => api_exigir_csrf());
assert_igual([false, 403], [$r['resultado'], $r['estado']], 'csrf: sin token en la sesión nada pasa');
unset($_SERVER['HTTP_X_CSRF_TOKEN']);
http_response_code(200);

// Que ningún endpoint vuelva a armar lo suyo: cada cosa se hace en un solo lugar.
$fuentes = fuentes_php(realpath(__DIR__ . '/../../servidor-php'));
$endpoints = array_filter($fuentes, fn ($ruta) => str_starts_with($ruta, 'codigo/api/') || str_starts_with($ruta, 'publico/'), ARRAY_FILTER_USE_KEY);
assert_verdadero(count($endpoints) >= 8, 'api: el recorrido encuentra los endpoints (' . count($endpoints) . ')');
assert_igual([], archivos_con_patron($fuentes, '/json_encode\(/', ['api_responder']), 'api: toda respuesta se serializa con api_responder(), ningún json_encode suelto');
assert_igual([], archivos_con_patron($fuentes, '#file_get_contents\(\s*[\'"]php://input#', ['api_cuerpo_json']), 'api: el cuerpo se lee solo con api_cuerpo_json()');
assert_igual([], archivos_con_patron($endpoints, '/auth_validar_csrf_header\(/', ['api_exigir_csrf']), 'api: el CSRF se exige solo con api_exigir_csrf()');
assert_igual(
    [],
    archivos_con_patron($endpoints, "/'csrf_invalido'|'metodo_no_permitido'/", ['api_exigir_csrf', 'api_metodo_no_permitido']),
    'api: el 403 de CSRF y el 405 de método salen de sus helpers, ningún endpoint repite el par código/mensaje'
);
