<?php

declare(strict_types=1);

/**
 * Orquesta el saneamiento de un registro de acción completo, combinando las
 * primitivas (primitivas.php, marca-temporal.php) según el esquema canónico.
 */

const SANEADOR_TIPOS_VALIDOS = [
    'login', 'search', 'view_product', 'add_to_cart', 'checkout_start', 'payment',
    'support_ticket', 'logout', 'password_reset', 'profile_update', 'review_submit',
    'api_call', 'file_upload', 'refund',
];

const SANEADOR_TIPOS_CON_MONTO = ['payment', 'refund'];

/**
 * Sanea un registro crudo de acción hacia el esquema canónico. Devuelve null si
 * el registro no es recuperable (le faltan campos esenciales o son inválidos).
 *
 * @param array $crudo Registro tal como "llegó", con formatos inconsistentes
 * @param array{
 *   usuarios_por_id: array<string,array>,
 *   monedas_validas: string[],
 *   moneda_por_pais: array<string,string>,
 *   tasa_por_moneda: array<string,float>,
 *   etiquetas: array<string,string>
 * } $contexto
 */
function saneador_accion(array $crudo, array $contexto): ?array
{
    $userId = saneador_id_usuario($crudo['user_id'] ?? null);
    $tipo = saneador_tipo_accion($crudo['type'] ?? null);
    $timestamp = saneador_marca_temporal($crudo['timestamp'] ?? null);
    $duracion = saneador_duracion_ms($crudo['duration_ms'] ?? null);

    if ($userId === null || $tipo === null || $timestamp === null || $duracion === null) {
        return null;
    }
    $usuario = $contexto['usuarios_por_id'][$userId] ?? null;
    if ($usuario === null) {
        return null;
    }

    $limpio = [
        'id' => is_string($crudo['id'] ?? null) ? $crudo['id'] : null,
        'user_id' => $userId,
        'type' => $tipo,
        'label' => $contexto['etiquetas'][$tipo] ?? $tipo,
        'timestamp' => $timestamp,
        'duration_ms' => $duracion,
        'amount_local' => null,
        'currency' => null,
        'amount_usd' => null,
        'comment' => null,
        'endpoint' => null,
        'http_status' => null,
        'file_size_kb' => null,
    ];

    if (in_array($tipo, SANEADOR_TIPOS_CON_MONTO, true)) {
        $monto = saneador_numero($crudo['amount'] ?? null);
        if ($monto !== null && $monto > 0) {
            // Si la moneda cruda es inválida, se asume la moneda del país del usuario
            // en vez de descartar el pago entero: el monto sí se registró.
            $moneda = saneador_moneda($crudo['currency'] ?? null, $contexto['monedas_validas'])
                ?? $contexto['moneda_por_pais'][$usuario['country']]
                ?? 'USD';
            $tasa = $contexto['tasa_por_moneda'][$moneda] ?? 1.0;
            $limpio['amount_local'] = round($monto, 2);
            $limpio['currency'] = $moneda;
            $limpio['amount_usd'] = round($monto / $tasa, 2);
        }
    }

    if ($tipo === 'review_submit' || $tipo === 'support_ticket') {
        $limpio['comment'] = saneador_texto($crudo['comment'] ?? null, 300);
    }

    if ($tipo === 'api_call') {
        $limpio['endpoint'] = saneador_texto($crudo['endpoint'] ?? null, 120);
        $limpio['http_status'] = saneador_codigo_http($crudo['http_status'] ?? null);
    }

    if (isset($crudo['file_size_kb'])) {
        $tamano = saneador_numero($crudo['file_size_kb']);
        $limpio['file_size_kb'] = ($tamano !== null && $tamano > 0) ? round($tamano, 1) : null;
    }

    return $limpio;
}
