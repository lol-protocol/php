<?php

declare(strict_types=1);

require_once __DIR__ . '/ayudantes.php';
require_once __DIR__ . '/generar-flujo.php';
require_once __DIR__ . '/generar-registro.php';

/**
 * Genera todas las acciones crudas: cada usuario tiene 1-3 sesiones, cada sesión
 * tiene su propia IP/país-de-IP/proveedor y un flujo de acciones con su propio
 * registro (formato inconsistente a propósito).
 *
 * @param array $users
 * @param array $tiposAccion
 * @param array{tiposConMonto: string[], comentarios: array, apiEndpoints: string[], httpStatusPool: int[]} $catalogos
 * @param array<string,string> $currencyByCountry
 * @param array<string,float> $rateToUsd
 * @param array<string,float> $countryPriceFactor
 * @param string[] $allCountries
 * @param string[] $proveedoresIsp
 * @param int $ahora Ancla fija (no time()) para que las fechas generadas sean
 *   reproducibles byte a byte entre corridas con la misma semilla.
 * @return array
 */
function generar_acciones_crudas(
    array $users,
    array $tiposAccion,
    array $catalogos,
    array $currencyByCountry,
    array $rateToUsd,
    array $countryPriceFactor,
    array $allCountries,
    array $proveedoresIsp,
    int $ahora
): array {
    $crudas = [];
    $seq = 1;

    foreach ($users as $user) {
        $moneda = $currencyByCountry[$user['country']] ?? 'USD';
        $contextoBase = [
            'speedFactor' => max(0.5, 0.75 + (($user['age'] - 30) / 120) + (mt_rand(-15, 15) / 100)),
            'priceFactorUsd' => $countryPriceFactor[$user['country']],
            'moneda' => $moneda,
            'tasaMoneda' => $rateToUsd[$moneda] ?? 1.0,
        ];

        $sessions = mt_rand(1, 3);
        for ($s = 1; $s <= $sessions; $s++) {
            // La IP (y su país) se fija por sesión: 80% coincide con el país
            // declarado del usuario, 20% no (simula VPN/proxy/viaje).
            $contextoSesion = $contextoBase + [
                'ip' => mt_rand(1, 223) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254),
                'ipCountry' => tal_vez(80) ? $user['country'] : $allCountries[array_rand($allCountries)],
                'ipIsp' => $proveedoresIsp[array_rand($proveedoresIsp)],
            ];

            $cursor = $ahora - mt_rand(1, 30) * 86400 - mt_rand(0, 86399);
            foreach (generar_flujo_sesion() as $tipo) {
                $resultado = generar_registro_crudo(
                    $user['id'], $tipo, $tiposAccion[$tipo], $cursor, $contextoSesion, $catalogos, $seq++
                );
                $crudas[] = $resultado['registro'];
                $cursor = $resultado['cursor'];
            }
        }
    }

    return $crudas;
}
