<?php

declare(strict_types=1);

/**
 * Pasa todas las acciones crudas por el saneador (saneador.php) y arma el
 * contexto de saneamiento (usuarios, monedas válidas, etiquetas por tipo).
 *
 * @return array{acciones: array, descartadas: int}
 */
function sanear_acciones(
    array $crudas,
    array $usersById,
    array $currencyByCountry,
    array $rateToUsd,
    array $tiposAccion
): array {
    $contexto = [
        'usuarios_por_id' => $usersById,
        'monedas_validas' => array_values(array_unique(array_values($currencyByCountry))),
        'moneda_por_pais' => $currencyByCountry,
        'tasa_por_moneda' => $rateToUsd,
        'etiquetas' => array_map(fn ($meta) => $meta['label'], $tiposAccion),
    ];

    $acciones = [];
    $descartadas = 0;
    foreach ($crudas as $crudo) {
        $saneada = saneador_accion($crudo, $contexto);
        if ($saneada === null) {
            $descartadas++;
            continue;
        }
        $acciones[] = $saneada;
    }
    usort($acciones, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

    return ['acciones' => $acciones, 'descartadas' => $descartadas];
}
