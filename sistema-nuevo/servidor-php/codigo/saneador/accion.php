<?php

declare(strict_types=1);

/**
 * Orquesta el saneamiento de un registro de acción completo, combinando las
 * primitivas y los sub-saneadores (accion-monto.php, accion-campos.php,
 * accion-ip.php) según el esquema canónico.
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
 *   etiquetas: array<string,string>,
 *   rutas: array<string,string>,
 *   paises_validos: string[],
 *   offset_por_pais: array<string,float>
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
        'path' => saneador_texto($crudo['path'] ?? null, 160) ?? ($contexto['rutas'][$tipo] ?? '/desconocido'),
        'amount_local' => null,
        'currency' => null,
        'amount_usd' => null,
        'comment' => null,
        'endpoint' => null,
        'http_status' => null,
        'file_size_kb' => null,
        'ip' => null,
        'ip_country' => null,
        'ip_local_time' => null,
        'ip_isp' => null,
    ];

    saneador_aplicar_monto($limpio, $crudo, $tipo, $usuario, $contexto);
    saneador_aplicar_campos_tipo($limpio, $crudo, $tipo);
    saneador_aplicar_ip($limpio, $crudo, $timestamp, $contexto);

    return $limpio;
}
