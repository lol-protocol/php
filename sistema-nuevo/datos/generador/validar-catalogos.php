<?php

declare(strict_types=1);

/**
 * Todo país que aparece en un preset de grupo tiene que existir en
 * countryNames/currencyByCountry/offsetPorPais. Sin esto, un país "fantasma"
 * pasaba desapercibido en JSON (sin claves foráneas) y recién explotaba como
 * error de PostgreSQL al cargar grupo_pais — mejor fallar acá, apenas se
 * cargan los catálogos, con un mensaje que dice exactamente qué falta.
 */
function validar_catalogos(array $catalogoPaises, array $catalogoMonedas, array $catalogoRed): void
{
    $faltantes = [];
    foreach ($catalogoPaises['presets'] as $clave => $preset) {
        foreach ($preset['countries'] as $codigo) {
            $completo = isset($catalogoPaises['countryNames'][$codigo])
                && isset($catalogoMonedas['currencyByCountry'][$codigo])
                && isset($catalogoRed['offsetPorPais'][$codigo]);
            if (!$completo) {
                $faltantes[$codigo][] = $clave;
            }
        }
    }

    if ($faltantes === []) {
        return;
    }
    $detalle = [];
    foreach ($faltantes as $codigo => $presets) {
        $detalle[] = "$codigo (preset: " . implode(', ', array_unique($presets)) . ')';
    }
    throw new RuntimeException(
        'Países en un preset pero ausentes de countryNames/currencyByCountry/offsetPorPais: '
        . implode('; ', $detalle)
    );
}
