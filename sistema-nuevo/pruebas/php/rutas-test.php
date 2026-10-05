<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/api.php';

// La tabla de rutas (servidor-php/codigo/rutas.php) es la única lista de qué función atiende cada ruta y de cuáles
// exigen sesión. Antes eran tres cosas de index.php que había que mantener a la par: la lista de rutas protegidas, la
// de las que dejan la sesión abierta y el if/elseif que despachaba. Agregar un endpoint y olvidarse de la primera lo
// dejaba abierto a cualquiera sin que ninguna prueba fallara. Ahora toda ruta exige sesión salvo que la tabla diga
// lo contrario, y el olvido es al revés: un endpoint "de más" protegido, que se nota en el primer pedido.

// Resolución: cada ruta cae en su función; las de id llevan el id como entero.
assert_igual(
    ['handler' => 'api_users', 'args' => [], 'sesion' => false],
    api_resolver_ruta('/api/users'),
    'rutas: /api/users -> api_users(), sin argumentos y exigiendo sesión'
);
assert_igual('api_alertas_config', api_resolver_ruta('/api/alerts-config')['handler'] ?? null, 'rutas: /api/alerts-config (con guion) -> api_alertas_config()');
assert_igual('api_filtros', api_resolver_ruta('/api/filtros')['handler'] ?? null, 'rutas: /api/filtros (sin id) -> api_filtros()');
assert_igual(
    ['handler' => 'api_filtros_delete', 'args' => [42], 'sesion' => false],
    api_resolver_ruta('/api/filtros/42'),
    'rutas: /api/filtros/42 -> api_filtros_delete(42), con el id como entero y exigiendo sesión'
);
foreach (['/api/login' => 'api_login', '/api/logout' => 'api_logout', '/api/session' => 'api_session'] as $path => $handler) {
    assert_igual(
        ['handler' => $handler, 'args' => [], 'sesion' => true],
        api_resolver_ruta($path),
        "rutas: $path -> $handler(), maneja la sesión por su cuenta"
    );
}

// Lo que no es exactamente una ruta de la tabla no se resuelve a ninguna (404): sin barra final, sin mayúsculas, sin
// id que no sea un entero, sin nada antes ni después. Incluido el salto de línea final, que `$` de una regex deja pasar.
$noSonRutas = [
    '', '/', '/api', '/api/', '/api/nada', '/api/users/', '/api/USERS', '/api/users/1', '/x/api/users', "/api/users\n",
    '/api/filtros/', '/api/filtros/abc', '/api/filtros/1x', '/api/filtros/-1', '/api/filtros/1/2', '/api/filtros/1.5', "/api/filtros/5\n",
];
foreach ($noSonRutas as $path) {
    assert_igual(null, api_resolver_ruta($path), 'rutas: ' . var_export($path, true) . ' no es ninguna ruta de la API');
}

// La tabla en sí: cada entrada se resuelve a sí misma, su función existe y recibe justo los argumentos que la ruta
// captura. Que una función con otro nombre o con otra firma no pase sin que nada lo note.
$manejanSesion = [];
foreach (API_RUTAS as $patron => $entrada) {
    assert_verdadero(preg_match('#^/api/[a-z-]+(/\{id\})?$#', $patron) === 1, "rutas: $patron tiene el formato /api/<nombre> o /api/<nombre>/{id}");

    $ruta = api_resolver_ruta(str_replace('{id}', '1', $patron));
    assert_igual($entrada[0], $ruta['handler'] ?? null, "rutas: $patron se resuelve a su propia entrada (ninguna otra la tapa)");
    if (($ruta['sesion'] ?? false) === true) {
        $manejanSesion[] = $patron;
    }

    assert_verdadero(function_exists($entrada[0]), "rutas: $patron -> {$entrada[0]}() existe");
    if (!function_exists($entrada[0])) {
        continue;
    }
    $parametros = (new ReflectionFunction($entrada[0]))->getParameters();
    assert_igual(
        array_fill(0, substr_count($patron, '{id}'), 'int'),
        array_map(fn (ReflectionParameter $p) => (string) $p->getType(), $parametros),
        "rutas: {$entrada[0]}() recibe un int por cada {id} de $patron, y nada más"
    );
}
assert_igual(
    ['/api/login', '/api/logout', '/api/session'],
    $manejanSesion,
    'rutas: solo login, logout y session manejan la sesión por su cuenta; toda otra ruta exige sesión'
);

// Que nadie vuelva a listar rutas por su cuenta: el front controller y los endpoints no escriben ninguna. Se miran los
// textos entre comillas (no los comentarios, que sí nombran rutas) menos los require de api/<archivo>.php.
$conRutas = [];
foreach (fuentes_php(realpath(__DIR__ . '/../../servidor-php')) as $archivo => $fuente) {
    foreach (literales_de_texto($fuente) as $texto) {
        if (preg_match('#/api/[a-z]#', $texto) === 1 && !str_ends_with($texto, '.php')) {
            $conRutas[$archivo] = true;
        }
    }
}
assert_igual(['codigo/rutas.php'], array_keys($conRutas), 'rutas: las rutas se escriben solo en rutas.php, ni index.php ni los endpoints tienen las suyas');
