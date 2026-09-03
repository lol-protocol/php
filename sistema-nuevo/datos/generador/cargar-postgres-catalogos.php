<?php

declare(strict_types=1);

/** Carga las tablas de catálogo (monedas, países, grupos, tipos de acción). */

function cargar_monedas(PDO $pdo, array $rateToUsd): void
{
    $stmt = $pdo->prepare('INSERT INTO monedas (codigo, tasa_a_usd) VALUES (:codigo, :tasa)');
    foreach ($rateToUsd as $codigo => $tasa) {
        $stmt->execute(['codigo' => $codigo, 'tasa' => $tasa]);
    }
}

function cargar_paises(PDO $pdo, array $countryNames, array $currencyByCountry, array $offsetPorPais): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO paises (codigo, nombre, moneda_codigo, offset_utc_horas)
         VALUES (:codigo, :nombre, :moneda, :offset)'
    );
    foreach ($countryNames as $codigo => $nombre) {
        $stmt->execute([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'moneda' => $currencyByCountry[$codigo] ?? 'USD',
            'offset' => $offsetPorPais[$codigo] ?? 0,
        ]);
    }
}

function cargar_grupos(PDO $pdo, array $presets): void
{
    $stmtGrupo = $pdo->prepare('INSERT INTO grupos_paises (clave, etiqueta) VALUES (:clave, :etiqueta)');
    $stmtMiembro = $pdo->prepare('INSERT INTO grupo_pais (grupo_clave, pais_codigo) VALUES (:grupo, :pais)');
    foreach ($presets as $clave => $preset) {
        $stmtGrupo->execute(['clave' => $clave, 'etiqueta' => $preset['label']]);
        foreach ($preset['countries'] as $pais) {
            $stmtMiembro->execute(['grupo' => $clave, 'pais' => $pais]);
        }
    }
}

function cargar_tipos_accion(PDO $pdo, array $tiposAccion): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO tipos_accion (clave, etiqueta, ruta_base, duracion_base_ms, tiene_monto)
         VALUES (:clave, :etiqueta, :ruta, :duracion, :monto)'
    );
    foreach ($tiposAccion as $clave => $meta) {
        $stmt->execute([
            'clave' => $clave,
            'etiqueta' => $meta['label'],
            'ruta' => $meta['ruta'],
            'duracion' => (int) $meta['base_ms'],
            // PDO manda bool como cadena vacía/"1" con pgsql; Postgres solo acepta eso último.
            'monto' => isset($meta['monto_base']) ? 'true' : 'false',
        ]);
    }
}
