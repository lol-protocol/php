<?php

declare(strict_types=1);

require_once __DIR__ . '/insertar-filas.php';

/** Carga las tablas de catálogo (monedas, países, grupos, tipos de acción). */

function cargar_monedas(PDO $pdo, array $rateToUsd): void
{
    $filas = [];
    foreach ($rateToUsd as $codigo => $tasa) {
        $filas[] = ['codigo' => $codigo, 'tasa_a_usd' => $tasa];
    }
    insertar_filas($pdo, 'monedas', $filas);
}

function cargar_paises(PDO $pdo, array $countryNames, array $currencyByCountry, array $offsetPorPais): void
{
    $filas = [];
    foreach ($countryNames as $codigo => $nombre) {
        $filas[] = [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'moneda_codigo' => $currencyByCountry[$codigo] ?? 'USD',
            'offset_utc_horas' => $offsetPorPais[$codigo] ?? 0,
        ];
    }
    insertar_filas($pdo, 'paises', $filas);
}

function cargar_grupos(PDO $pdo, array $presets): void
{
    $grupos = [];
    $miembros = [];
    foreach ($presets as $clave => $preset) {
        $grupos[] = ['clave' => $clave, 'etiqueta' => $preset['label']];
        foreach ($preset['countries'] as $pais) {
            $miembros[] = ['grupo_clave' => $clave, 'pais_codigo' => $pais];
        }
    }
    insertar_filas($pdo, 'grupos_paises', $grupos);
    insertar_filas($pdo, 'grupo_pais', $miembros);
}

function cargar_tipos_accion(PDO $pdo, array $tiposAccion): void
{
    $filas = [];
    foreach ($tiposAccion as $clave => $meta) {
        $filas[] = [
            'clave' => $clave,
            'etiqueta' => $meta['label'],
            'ruta_base' => $meta['ruta'],
            'duracion_base_ms' => (int) $meta['base_ms'],
            // PDO manda bool como cadena vacía/"1" con pgsql; Postgres solo acepta eso último.
            'tiene_monto' => isset($meta['monto_base']) ? 'true' : 'false',
        ];
    }
    insertar_filas($pdo, 'tipos_accion', $filas);
}
