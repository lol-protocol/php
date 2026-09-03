<?php

declare(strict_types=1);

require_once __DIR__ . '/ayudantes.php';
require_once __DIR__ . '/crudo.php';

/**
 * Arma un registro crudo (formato inconsistente a propósito) para una acción.
 *
 * @param array{
 *   speedFactor: float, priceFactorUsd: float, moneda: string, tasaMoneda: float,
 *   ip: string, ipCountry: string, ipIsp: string
 * } $contextoUsuario
 * @param array{tiposConMonto: string[], comentarios: array, apiEndpoints: string[], httpStatusPool: int[]} $catalogos
 * @return array{registro: array, cursor: int}
 */
function generar_registro_crudo(
    string $userId,
    string $tipo,
    array $meta,
    int $cursor,
    array $contextoUsuario,
    array $catalogos,
    int $seq
): array {
    $cursor += mt_rand(3, 90); // "tiempo de pensar" entre acciones
    $duracionMs = $meta['base_ms'] * $contextoUsuario['speedFactor'] * (mt_rand(60, 140) / 100);

    $registro = [
        'id' => sprintf('a%05d', $seq),
        'user_id' => tal_vez(2) ? '' : crudo_espacios($userId),
        'type' => tal_vez(2) ? 'evento_desconocido' : crudo_espacios(crudo_may_min($tipo)),
        'timestamp' => tal_vez(2) ? 'fecha-invalida' : crudo_marca_temporal($cursor),
        'duration_ms' => tal_vez(3) ? (tal_vez(50) ? 'N/D' : -$duracionMs) : crudo_numero($duracionMs, 0),
        'path' => $meta['ruta'],
        'ip' => tal_vez(5) ? ($contextoUsuario['ip'] . ':' . mt_rand(1024, 65000)) : $contextoUsuario['ip'],
        'ip_country' => crudo_espacios(crudo_may_min($contextoUsuario['ipCountry'])),
        'ip_isp' => crudo_espacios($contextoUsuario['ipIsp']),
    ];

    if (in_array($tipo, $catalogos['tiposConMonto'], true)) {
        $montoUsdObjetivo = $meta['monto_base'] * $contextoUsuario['priceFactorUsd'] * (mt_rand(50, 180) / 100);
        $montoLocal = $montoUsdObjetivo * $contextoUsuario['tasaMoneda'];
        $monedasInvalidas = ['xxx', 'N/A', ''];
        $registro['amount'] = tal_vez(3) ? 'N/D' : crudo_numero($montoLocal, 2);
        $registro['currency'] = tal_vez(5)
            ? $monedasInvalidas[array_rand($monedasInvalidas)]
            : crudo_may_min($contextoUsuario['moneda']);
    }

    if ($tipo === 'review_submit' || ($tipo === 'support_ticket' && tal_vez(60))) {
        $registro['comment'] = crudo_comentario($catalogos['comentarios']['base'], $catalogos['comentarios']['inseguros']);
    }

    if ($tipo === 'api_call') {
        $registro['endpoint'] = $catalogos['apiEndpoints'][array_rand($catalogos['apiEndpoints'])];
        $status = $catalogos['httpStatusPool'][array_rand($catalogos['httpStatusPool'])];
        $registro['http_status'] = tal_vez(80) ? $status : (string) $status;
    }

    if ($tipo === 'file_upload') {
        $tamanoKb = mt_rand(50, 8000) / 10;
        $registro['file_size_kb'] = tal_vez(30) ? ($tamanoKb . ' KB') : $tamanoKb;
    }

    return ['registro' => $registro, 'cursor' => $cursor + (int) round($duracionMs / 1000)];
}
