<?php

declare(strict_types=1);

/**
 * Tabla de rutas de la API: la única lista de qué función atiende cada ruta y cuáles exigen sesión.
 *
 * Cada entrada es [función] o [función, 'sesion']. Una ruta exige sesión iniciada salvo que lleve 'sesion': esas
 * (login, logout y session) manejan la sesión por su cuenta, así que no pasan por la barrera de autenticación y la
 * dejan abierta para escribirla. Por eso agregar un endpoint y no tocar nada más lo deja protegido; lo que se
 * olvida es, a lo sumo, una ruta de más con sesión, nunca una abierta a cualquiera.
 *
 * "{id}" captura un entero y se lo pasa a la función como argumento. El método HTTP lo verifica cada función
 * (api_exigir_metodo), no la tabla.
 */
const API_RUTAS = [
    '/api/login' => ['api_login', 'sesion'],
    '/api/logout' => ['api_logout', 'sesion'],
    '/api/session' => ['api_session', 'sesion'],
    '/api/users' => ['api_users'],
    '/api/groups' => ['api_groups'],
    '/api/action-types' => ['api_action_types'],
    '/api/alerts' => ['api_alerts'],
    '/api/alerts-config' => ['api_alertas_config'],
    '/api/filtros' => ['api_filtros'],
    '/api/filtros/{id}' => ['api_filtros_delete'],
    '/api/notes' => ['api_notas'],
    '/api/kpis' => ['api_kpis'],
    '/api/timeline' => ['api_timeline'],
];

/**
 * La ruta de la tabla que corresponde a $path (idéntica, sin nada antes ni después), o null si no hay ninguna.
 *
 * @return array{handler:string,args:int[],sesion:bool}|null
 */
function api_resolver_ruta(string $path): ?array
{
    foreach (API_RUTAS as $patron => $entrada) {
        // La D evita que `$` acepte un salto de línea final: "/api/users\n" no es "/api/users".
        $regex = '#^' . str_replace('\{id\}', '(\d+)', preg_quote($patron, '#')) . '$#D';
        if (preg_match($regex, $path, $coincidencias) === 1) {
            return [
                'handler' => $entrada[0],
                'args' => array_map('intval', array_slice($coincidencias, 1)),
                'sesion' => ($entrada[1] ?? null) === 'sesion',
            ];
        }
    }
    return null;
}
