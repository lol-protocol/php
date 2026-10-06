<?php

declare(strict_types=1);

/**
 * Sobre valores exactos (no invariantes): igual que almacen-kpis-test.php,
 * se testean invariantes y forma de la respuesta, no números pelados -- así
 * esta prueba no se rompe si el generador de datos semilla cambia sin que
 * AlmacenAlertas tenga ningún bug real.
 */

$almacen = new AlmacenAlertas($pdo);

$ip = $almacen->ipMismatches();
assert_verdadero($ip['total_events'] >= 0, 'alertas: ipMismatches total_events nunca negativo');
assert_verdadero($ip['total_users_affected'] >= 0, 'alertas: ipMismatches total_users_affected nunca negativo');
assert_verdadero(count($ip['top']) <= 15, 'alertas: ipMismatches top nunca trae más de 15 (LIMITE_USUARIOS)');
if ($ip['top']) {
    $primero = $ip['top'][0];
    assert_verdadero(
        isset($primero['user_id'], $primero['user_name'], $primero['country'], $primero['event_count'], $primero['last_seen']),
        'alertas: cada fila de ipMismatches trae todos los campos esperados'
    );
    for ($i = 1; $i < count($ip['top']); $i++) {
        assert_verdadero(
            $ip['top'][$i - 1]['event_count'] >= $ip['top'][$i]['event_count'],
            'alertas: ipMismatches.top viene ordenado de mayor a menor'
        );
    }
}

$cambiosSensibilidadBaja = $almacen->cambiosPaisImposibles(0);
$cambiosSensibilidadAlta = $almacen->cambiosPaisImposibles(100);
assert_verdadero(
    $cambiosSensibilidadAlta['total_events'] >= $cambiosSensibilidadBaja['total_events'],
    'alertas: más sensibilidad detecta al menos tantos cambios de país como menos sensibilidad (ventana de tiempo más amplia)'
);
if ($cambiosSensibilidadAlta['top']) {
    $primero = $cambiosSensibilidadAlta['top'][0];
    assert_verdadero(
        isset($primero['user_id'], $primero['user_name'], $primero['previous_country'], $primero['current_country'], $primero['event_count'], $primero['last_seen']),
        'alertas: cada fila de cambiosPaisImposibles trae todos los campos esperados'
    );
    assert_verdadero($primero['previous_country'] !== $primero['current_country'], 'alertas: un "cambio" siempre es entre dos países distintos');
}

// LIMITE_USUARIOS limita usuarios distintos, no filas: un usuario con varios
// pares de país "imposibles" debe aparecer una sola vez en el top.
$idsEnTop = array_column($cambiosSensibilidadAlta['top'], 'user_id');
assert_igual(
    count($idsEnTop),
    count(array_unique($idsEnTop)),
    'alertas: cambiosPaisImposibles.top nunca repite el mismo usuario en dos filas'
);

// ipMismatches() agrupa vía GROUP BY + ORDER BY cantidad DESC, sin desempate
// no está garantizado qué orden queda entre dos usuarios empatados (probado
// aparte con EXPLAIN: un plan Sort+GroupAggregate y uno HashAggregate pueden
// dar órdenes distintos para el mismo empate). Acá solo se confirma el
// contrato ya arreglado: empatados en cantidad, el de id menor va primero.
// 20 mismatches cada uno supera cómodamente el máximo real actual, así que
// ambos entran garantizado en el top-15, en las posiciones 0-1.
$pais = $pdo->query('SELECT codigo FROM paises LIMIT 1')->fetchColumn();
$otroPais = $pdo->query("SELECT codigo FROM paises WHERE codigo <> '$pais' LIMIT 1")->fetchColumn();
$pdo->prepare('INSERT INTO usuarios (id, nombre, pais_codigo, edad, genero) VALUES (?, ?, ?, 30, \'O\'), (?, ?, ?, 30, \'O\')')
    ->execute(['zA01', 'Empate zA01', $pais, 'zA02', 'Empate zA02', $pais]);
$pdo->prepare(
    "INSERT INTO acciones (id, usuario_id, tipo_clave, marca_temporal, duracion_ms, ruta, ip, ip_pais_codigo)
     SELECT uid || LPAD(n::text, 2, '0'), uid, 'login', NOW() - (n || ' hours')::interval, 100, '/t', '1.1.1.1', ?
     FROM generate_series(1, 20) n, (VALUES ('zA01'), ('zA02')) AS usuarios_prueba(uid)"
)->execute([$otroPais]);

$empatados = array_slice($almacen->ipMismatches()['top'], 0, 2);

$pdo->exec("DELETE FROM acciones WHERE usuario_id IN ('zA01', 'zA02')");
$pdo->exec("DELETE FROM usuarios WHERE id IN ('zA01', 'zA02')");

assert_igual(['zA01', 'zA02'], array_column($empatados, 'user_id'), 'alertas: dos usuarios empatados en event_count se ordenan por id');
// Y cada fila trae cuántas acciones tuvo y el país que declaró el usuario (no el de la IP, que es $otroPais).
assert_igual(
    [[20, $pais], [20, $pais]],
    array_map(fn (array $fila) => [$fila['event_count'], $fila['country']], $empatados),
    'alertas: cada fila de IP fuera del país trae la cantidad de acciones y el país declarado, no el de la IP'
);

// Las dos alertas hablan el mismo idioma: el mismo sobre y las mismas columnas comunes en cada fila (usuario, cantidad y
// última vez), con lo propio de cada una aparte y al final. Antes cada una nombraba distinto lo mismo (total_mismatches /
// total_changes, mismatch_count / cambio_count, country / pais_anterior) y el frontend tenía que adivinar cuál era cuál.
$ipTop = $almacen->ipMismatches();
$cambiosTop = $almacen->cambiosPaisImposibles(100);
assert_igual(['total_events', 'total_users_affected', 'top'], array_keys($ipTop), 'alertas: el sobre de ipMismatches');
assert_igual(array_keys($ipTop), array_keys($cambiosTop), 'alertas: las dos alertas devuelven el mismo sobre');
assert_verdadero($ipTop['top'] !== [] && $cambiosTop['top'] !== [], 'alertas: la semilla tiene casos de las dos alertas (si no, lo de abajo no prueba nada)');
$comunes = ['user_id', 'user_name', 'event_count', 'last_seen'];
assert_igual($comunes, array_slice(array_keys($ipTop['top'][0]), 0, 4), 'alertas: las filas de ipMismatches empiezan por las columnas comunes');
assert_igual($comunes, array_slice(array_keys($cambiosTop['top'][0]), 0, 4), 'alertas: las filas de cambiosPaisImposibles empiezan por las mismas columnas comunes');
assert_igual(['country'], array_slice(array_keys($ipTop['top'][0]), 4), 'alertas: lo propio de una IP fuera del país es el país declarado');
assert_igual(['previous_country', 'current_country'], array_slice(array_keys($cambiosTop['top'][0]), 4), 'alertas: lo propio de un cambio de país es de dónde y a dónde');
