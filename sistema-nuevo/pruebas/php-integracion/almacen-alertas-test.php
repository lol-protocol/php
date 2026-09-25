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
assert_verdadero($ip['total_mismatches'] >= 0, 'alertas: ipMismatches total_mismatches nunca negativo');
assert_verdadero($ip['total_users_affected'] >= 0, 'alertas: ipMismatches total_users_affected nunca negativo');
assert_verdadero(count($ip['top']) <= 15, 'alertas: ipMismatches top nunca trae más de 15 (LIMITE_USUARIOS)');
if ($ip['top']) {
    $primero = $ip['top'][0];
    assert_verdadero(
        isset($primero['user_id'], $primero['user_name'], $primero['country'], $primero['mismatch_count'], $primero['last_seen']),
        'alertas: cada fila de ipMismatches trae todos los campos esperados'
    );
    for ($i = 1; $i < count($ip['top']); $i++) {
        assert_verdadero(
            $ip['top'][$i - 1]['mismatch_count'] >= $ip['top'][$i]['mismatch_count'],
            'alertas: ipMismatches.top viene ordenado de mayor a menor'
        );
    }
}

$cambiosSensibilidadBaja = $almacen->cambiosPaisImposibles(0);
$cambiosSensibilidadAlta = $almacen->cambiosPaisImposibles(100);
assert_verdadero(
    $cambiosSensibilidadAlta['total_changes'] >= $cambiosSensibilidadBaja['total_changes'],
    'alertas: más sensibilidad detecta al menos tantos cambios de país como menos sensibilidad (ventana de tiempo más amplia)'
);
if ($cambiosSensibilidadAlta['top']) {
    $primero = $cambiosSensibilidadAlta['top'][0];
    assert_verdadero(
        isset($primero['user_id'], $primero['user_name'], $primero['pais_anterior'], $primero['pais_actual'], $primero['cambio_count'], $primero['last_seen']),
        'alertas: cada fila de cambiosPaisImposibles trae todos los campos esperados'
    );
    assert_verdadero($primero['pais_anterior'] !== $primero['pais_actual'], 'alertas: un "cambio" siempre es entre dos países distintos');
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

$ordenEnTop = array_slice(array_column($almacen->ipMismatches()['top'], 'user_id'), 0, 2);

$pdo->exec("DELETE FROM acciones WHERE usuario_id IN ('zA01', 'zA02')");
$pdo->exec("DELETE FROM usuarios WHERE id IN ('zA01', 'zA02')");

assert_igual(['zA01', 'zA02'], $ordenEnTop, 'alertas: dos usuarios empatados en mismatch_count se ordenan por id');
