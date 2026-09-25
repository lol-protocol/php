<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/ConexionBd.php';
require_once __DIR__ . '/cargar-postgres-catalogos.php';
require_once __DIR__ . '/cargar-postgres-nucleo.php';

/**
 * Recrea el esquema (esquema.sql + esquema-nucleo.sql, ambos con DROP/CREATE)
 * y carga en PostgreSQL los mismos datos que se escribieron a JSON/CSV. Se
 * corre una sola vez al final de generar-datos-semilla.php; el backend en
 * vivo (AlmacenDatos.php) después solo lee de acá, ya no de los JSON.
 */
function cargar_en_postgres(
    array $users,
    array $acciones,
    array $countryNames,
    array $currencyByCountry,
    array $rateToUsd,
    array $offsetPorPais,
    array $presets,
    array $tiposAccion
): void {
    $pdo = ConexionBd::obtener();
    $dataDir = __DIR__ . '/..';

    $pdo->exec((string) file_get_contents($dataDir . '/esquema.sql'));
    $pdo->exec((string) file_get_contents($dataDir . '/esquema-nucleo.sql'));

    $pdo->beginTransaction();
    try {
        cargar_monedas($pdo, $rateToUsd);
        cargar_paises($pdo, $countryNames, $currencyByCountry, $offsetPorPais);
        cargar_grupos($pdo, $presets);
        cargar_tipos_accion($pdo, $tiposAccion);
        cargar_usuarios($pdo, $users);
        cargar_administrador($pdo);
        cargar_acciones($pdo, $acciones);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
