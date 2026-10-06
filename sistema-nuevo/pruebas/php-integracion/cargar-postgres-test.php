<?php

declare(strict_types=1);

/** @var PDO $pdo */

require_once __DIR__ . '/../../datos/generador/cargar-postgres-catalogos.php';
require_once __DIR__ . '/../../datos/generador/cargar-postgres-nucleo.php';

// Los cargar_* de la siembra (datos/generador/cargar-postgres-*.php) vuelcan en PostgreSQL lo que generó el generador. Se
// prueban con datos mínimos, cada tabla contra lo que tiene que quedar, en un schema descartable que se revierte: así se
// ve qué columna recibe qué valor sin tocar la base real ni depender de qué datos haya sembrados.
$datos = __DIR__ . '/../../datos';
$credenciales = require __DIR__ . '/../../servidor-php/codigo/credenciales.php';
$leidas = [];
$filas = fn (string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_NUM);

$error = en_schema_descartable($pdo, 'carga_prueba', function () use ($pdo, $datos, $filas, &$leidas) {
    foreach (['esquema.sql', 'esquema-nucleo.sql'] as $archivo) {
        $pdo->exec((string) file_get_contents("$datos/$archivo"));
    }

    cargar_monedas($pdo, ['USD' => 1.0, 'ARS' => 1200.5]);
    // ZZ no figura en las monedas ni en los husos: cae a USD y a 0.
    cargar_paises(
        $pdo,
        ['AR' => 'Argentina', 'BR' => 'Brasil', 'ZZ' => 'Sin datos'],
        ['AR' => 'ARS', 'BR' => 'USD'],
        ['AR' => -3, 'BR' => -3.5]
    );
    cargar_grupos($pdo, [
        'latam' => ['label' => 'LATAM', 'countries' => ['AR', 'BR']],
        'vacio' => ['label' => 'Sin países', 'countries' => []],
    ]);
    cargar_tipos_accion($pdo, [
        'payment' => ['label' => 'Pago', 'ruta' => '/app/pago.php', 'base_ms' => 1200.7, 'monto_base' => 50],
        'login' => ['label' => 'Ingreso', 'ruta' => '/app/login.php', 'base_ms' => 300],
    ]);
    cargar_usuarios($pdo, [
        ['id' => 'u001', 'name' => 'Ana Pérez', 'country' => 'AR', 'age' => 31, 'gender' => 'F'],
        ['id' => 'u002', 'name' => 'Bruno Lima', 'country' => 'BR', 'age' => 45, 'gender' => 'M'],
    ]);
    cargar_administrador($pdo);
    cargar_acciones($pdo, [
        [
            'id' => 'a00001', 'user_id' => 'u001', 'type' => 'payment', 'timestamp' => '2026-03-01 10:00:00',
            'duration_ms' => 1500, 'path' => '/app/pago.php', 'ip' => '190.1.2.3', 'ip_country' => 'AR',
            'ip_local_time' => '07:00:00', 'ip_isp' => 'Telecom', 'amount_local' => 1200.5, 'currency' => 'ARS',
            'amount_usd' => 1.0, 'comment' => 'pago de prueba', 'endpoint' => '/v1/pagos', 'http_status' => 201,
            'file_size_kb' => 12.5,
        ],
        [
            'id' => 'a00002', 'user_id' => 'u002', 'type' => 'login', 'timestamp' => '2026-03-02 11:30:00',
            'duration_ms' => 250, 'path' => '/app/login.php', 'ip' => '2001:db8::1', 'ip_country' => null,
            'ip_local_time' => null, 'ip_isp' => null, 'amount_local' => null, 'currency' => null,
            'amount_usd' => null, 'comment' => null, 'endpoint' => null, 'http_status' => null,
            'file_size_kb' => null,
        ],
    ]);

    $leidas = [
        'monedas' => $filas('SELECT codigo, tasa_a_usd FROM monedas ORDER BY codigo'),
        'paises' => $filas('SELECT codigo, nombre, moneda_codigo, offset_utc_horas FROM paises ORDER BY codigo'),
        'grupos' => $filas('SELECT clave, etiqueta FROM grupos_paises ORDER BY clave'),
        'miembros' => $filas('SELECT grupo_clave, pais_codigo FROM grupo_pais ORDER BY 1, 2'),
        'tipos' => $filas('SELECT clave, etiqueta, ruta_base, duracion_base_ms, tiene_monto FROM tipos_accion ORDER BY clave'),
        'usuarios' => $filas('SELECT id, nombre, pais_codigo, edad, genero FROM usuarios ORDER BY id'),
        'administradores' => $filas('SELECT usuario, clave_hash FROM administradores'),
        'acciones' => $filas(
            "SELECT id, usuario_id, tipo_clave, to_char(marca_temporal, 'YYYY-MM-DD HH24:MI:SS'), duracion_ms, ruta, ip,
                    ip_pais_codigo, ip_hora_local::text, ip_proveedor, monto_local, moneda_codigo, monto_usd, comentario,
                    endpoint, codigo_http, tamano_archivo_kb
             FROM acciones ORDER BY id"
        ),
    ];
});

assert_igual(null, $error, 'cargar_*: los datos mínimos se cargan sin error (claves foráneas incluidas)');
assert_igual([['ARS', '1200.500000'], ['USD', '1.000000']], $leidas['monedas'], 'cargar_monedas: una fila por moneda, con su tasa');
assert_igual(
    [['AR', 'Argentina', 'ARS', '-3.00'], ['BR', 'Brasil', 'USD', '-3.50'], ['ZZ', 'Sin datos', 'USD', '0.00']],
    $leidas['paises'],
    'cargar_paises: nombre, moneda y huso de cada país; sin dato, USD y 0'
);
assert_igual([['latam', 'LATAM'], ['vacio', 'Sin países']], $leidas['grupos'], 'cargar_grupos: una fila por grupo, también el que no tiene países');
assert_igual([['latam', 'AR'], ['latam', 'BR']], $leidas['miembros'], 'cargar_grupos: una fila por país de cada grupo');
assert_igual(
    [['login', 'Ingreso', '/app/login.php', 300, false], ['payment', 'Pago', '/app/pago.php', 1200, true]],
    $leidas['tipos'],
    'cargar_tipos_accion: la duración base se trunca a entero y tiene_monto sale de si hay monto base'
);
assert_igual(
    [['u001', 'Ana Pérez', 'AR', 31, 'F'], ['u002', 'Bruno Lima', 'BR', 45, 'M']],
    $leidas['usuarios'],
    'cargar_usuarios: id, nombre, país, edad y género de cada usuario'
);
assert_igual(
    [[$credenciales['username'], $credenciales['password_hash']]],
    $leidas['administradores'],
    'cargar_administrador: el usuario y el hash de credenciales.php, una sola vez'
);
assert_igual(
    [
        [
            'a00001', 'u001', 'payment', '2026-03-01 10:00:00', 1500, '/app/pago.php', '190.1.2.3', 'AR', '07:00:00',
            'Telecom', '1200.50', 'ARS', '1.00', 'pago de prueba', '/v1/pagos', 201, '12.5',
        ],
        [
            'a00002', 'u002', 'login', '2026-03-02 11:30:00', 250, '/app/login.php', '2001:db8::1', null, null,
            null, null, null, null, null, null, null, null,
        ],
    ],
    $leidas['acciones'],
    'cargar_acciones: cada columna recibe su valor, y lo que no aplica a la acción queda NULL'
);
