<?php

declare(strict_types=1);

/**
 * Sobre valores exactos (no invariantes): igual que almacen-kpis-test.php,
 * se testean invariantes y forma de la respuesta, no números pelados -- así
 * esta prueba no se rompe si el generador de datos semilla cambia sin que
 * AlmacenAlertas tenga ningún bug real.
 */

$ip = AlmacenAlertas::ipMismatches();
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

$cambiosSensibilidadBaja = AlmacenAlertas::cambiosPaisImposibles(0);
$cambiosSensibilidadAlta = AlmacenAlertas::cambiosPaisImposibles(100);
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
