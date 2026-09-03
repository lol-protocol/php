<?php

declare(strict_types=1);

/**
 * Genera los datos semilla del backoffice en dos etapas: arma logs de acciones
 * "crudos" (formatos inconsistentes a propósito) y los sanea al esquema canónico
 * que usa el resto del sistema. La lógica vive modularizada en generador/*.php.
 *
 * Uso: php generar-datos-semilla.php
 */

require __DIR__ . '/../servidor-php/codigo/saneador.php';
require __DIR__ . '/generador/generar-usuarios.php';
require __DIR__ . '/generador/generar-acciones-crudas.php';
require __DIR__ . '/generador/sanear-acciones.php';
require __DIR__ . '/generador/escribir-archivos.php';

mt_srand(20260903); // semilla fija: datos reproducibles entre corridas
$ahora = strtotime('2026-09-03T12:00:00+00:00'); // ancla fija, no time(): reproducible byte a byte

$catalogoPaises = require __DIR__ . '/generador/catalogo-paises.php';
$catalogoMonedas = require __DIR__ . '/generador/catalogo-monedas.php';
$catalogoRed = require __DIR__ . '/generador/catalogo-red.php';
$nombres = require __DIR__ . '/generador/nombres.php';
$tiposAccionData = require __DIR__ . '/generador/tipos-accion.php';
$comentarios = require __DIR__ . '/generador/comentarios.php';

$allCountries = array_keys($catalogoPaises['countryNames']);

// Factor de "nivel de precio" determinístico por país (~0.7 - 1.6).
$countryPriceFactor = [];
foreach ($allCountries as $c) {
    $countryPriceFactor[$c] = 0.7 + ((crc32($c) % 100) / 100) * 0.9;
}

$users = generar_usuarios(60, $allCountries, $nombres);
$usersById = array_column($users, null, 'id');

$crudas = generar_acciones_crudas(
    $users,
    $tiposAccionData['tiposAccion'],
    [
        'tiposConMonto' => $tiposAccionData['tiposConMonto'],
        'comentarios' => $comentarios,
        'apiEndpoints' => $tiposAccionData['apiEndpoints'],
        'httpStatusPool' => $tiposAccionData['httpStatusPool'],
    ],
    $catalogoMonedas['currencyByCountry'],
    $catalogoMonedas['rateToUsd'],
    $countryPriceFactor,
    $allCountries,
    $catalogoRed['proveedoresIsp'],
    $ahora
);

$saneado = sanear_acciones(
    $crudas, $usersById, $catalogoMonedas['currencyByCountry'], $catalogoMonedas['rateToUsd'],
    $tiposAccionData['tiposAccion'], $catalogoRed['offsetPorPais']
);
$acciones = $saneado['acciones'];

$dataDir = __DIR__;
escribir_json($dataDir . '/usuarios.json', $users);
escribir_json($dataDir . '/acciones-crudas.json', $crudas);
escribir_json($dataDir . '/acciones.json', $acciones);
escribir_json($dataDir . '/grupos-de-paises.json', [
    'presets' => array_map(
        fn ($key) => [
            'key' => $key,
            'label' => $catalogoPaises['presets'][$key]['label'],
            'countries' => $catalogoPaises['presets'][$key]['countries'],
        ],
        array_keys($catalogoPaises['presets'])
    ),
    'countries' => $catalogoPaises['countryNames'],
]);
escribir_json($dataDir . '/monedas.json', [
    'currency_by_country' => $catalogoMonedas['currencyByCountry'],
    'rate_per_usd' => $catalogoMonedas['rateToUsd'],
]);
escribir_csv_acciones($dataDir . '/acciones-planas.csv', $acciones, $usersById);

fwrite(STDERR, sprintf(
    "Generados %d usuarios, %d acciones crudas -> %d saneadas, %d descartadas por datos inválidos.\n",
    count($users), count($crudas), count($acciones), $saneado['descartadas']
));
