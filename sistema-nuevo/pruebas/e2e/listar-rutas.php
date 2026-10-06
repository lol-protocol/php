<?php

declare(strict_types=1);

// Para peticiones-api.e2e.cjs: las rutas de la API tal como las ve el router, en JSON {"ruta": maneja_la_sesion}.
// Las lee de la tabla real (rutas.php) en vez de buscarlas con una regex en el código fuente.
require __DIR__ . '/../../servidor-php/codigo/rutas.php';

$rutas = [];
foreach (array_keys(API_RUTAS) as $patron) {
    $rutas[$patron] = api_resolver_ruta(str_replace('{id}', '1', $patron))['sesion'] ?? null;
}
echo json_encode($rutas, JSON_UNESCAPED_SLASHES);
