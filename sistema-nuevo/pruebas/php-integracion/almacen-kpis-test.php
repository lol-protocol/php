<?php

declare(strict_types=1);

/**
 * Sobre valores exactos (no invariantes): los datos semilla son deterministas
 * (semilla fija en generar-datos-semilla.php), pero igual se testean
 * invariantes en vez de números pelados -- así esta prueba no se rompe si el
 * generador cambia sin que AlmacenKpis tenga ningún bug.
 */
$kpis = AlmacenKpis::resumen();

assert_verdadero($kpis['total_users'] > 0, 'kpis: total_users positivo con los datos semilla cargados');
assert_verdadero($kpis['total_actions'] > 0, 'kpis: total_actions positivo con los datos semilla cargados');
assert_verdadero($kpis['total_spend_usd'] >= 0, 'kpis: total_spend_usd nunca negativo');
assert_verdadero($kpis['active_alerts_users'] >= 0, 'kpis: active_alerts_users nunca negativo');

assert_verdadero(count($kpis['top_action_types']) > 0, 'kpis: top_action_types no viene vacío');
$primero = $kpis['top_action_types'][0];
assert_verdadero(isset($primero['type'], $primero['count']), 'kpis: cada entrada de top_action_types trae type y count');

for ($i = 1; $i < count($kpis['top_action_types']); $i++) {
    assert_verdadero(
        $kpis['top_action_types'][$i - 1]['count'] >= $kpis['top_action_types'][$i]['count'],
        'kpis: top_action_types viene ordenado de mayor a menor'
    );
}

assert_verdadero(count($kpis['top_countries']) > 0, 'kpis: top_countries no viene vacío');
