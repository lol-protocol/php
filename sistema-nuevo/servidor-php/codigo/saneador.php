<?php

declare(strict_types=1);

/**
 * Saneamiento de logs de acciones "crudos": formatos de fecha inconsistentes,
 * números como texto, HTML/scripts colados en campos de texto libre, códigos
 * inválidos, campos faltantes. Convierte cada registro crudo al esquema canónico
 * que usa el resto del sistema, o lo descarta si no es recuperable.
 *
 * Se usa una sola vez al generar los datos semilla (ver generar-datos-semilla.php);
 * el backend en vivo ya lee datos limpios, no vuelve a sanear en cada request.
 */

const SANEADOR_TIPOS_VALIDOS = [
    'login', 'search', 'view_product', 'add_to_cart', 'checkout_start', 'payment',
    'support_ticket', 'logout', 'password_reset', 'profile_update', 'review_submit',
    'api_call', 'file_upload', 'refund',
];

const SANEADOR_TIPOS_CON_MONTO = ['payment', 'refund'];

function saneador_texto(mixed $valor, int $maxLargo = 500): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    // Decodifica entidades primero, así "&lt;script&gt;" también queda expuesto
    // como una etiqueta real antes de quitarla (evita una vía simple de evasión).
    $decodificado = html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $sinEtiquetas = strip_tags($decodificado);
    $colapsado = preg_replace('/\s+/u', ' ', $sinEtiquetas) ?? '';
    $limpio = trim($colapsado);

    return $limpio === '' ? null : mb_substr($limpio, 0, $maxLargo);
}

function saneador_id_usuario(mixed $valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $limpio = trim($valor);
    return preg_match('/^u\d{3}$/', $limpio) === 1 ? $limpio : null;
}

function saneador_tipo_accion(mixed $valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $limpio = strtolower(trim($valor));
    return in_array($limpio, SANEADOR_TIPOS_VALIDOS, true) ? $limpio : null;
}

function saneador_numero(mixed $valor): ?float
{
    if (is_int($valor) || is_float($valor)) {
        return (float) $valor;
    }
    if (!is_string($valor)) {
        return null;
    }
    // "$1,234.56" -> "1234.56"; deja pasar el signo para poder rechazar negativos después.
    $limpio = preg_replace('/[^\d.\-]/u', '', str_replace(',', '', trim($valor))) ?? '';
    return ($limpio !== '' && is_numeric($limpio)) ? (float) $limpio : null;
}

function saneador_duracion_ms(mixed $valor): ?int
{
    $numero = saneador_numero($valor);
    if ($numero === null || $numero <= 0 || $numero > 3_600_000) {
        return null; // duración inválida, negativa o mayor a 1 hora: no es recuperable
    }
    return (int) round($numero);
}

function saneador_marca_temporal(mixed $valor): ?string
{
    if (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric($valor))) {
        $numero = (float) $valor;
        // Heurística estándar para distinguir epoch en segundos de epoch en milisegundos.
        $segundos = $numero > 10_000_000_000 ? (int) round($numero / 1000) : (int) round($numero);
        return $segundos > 0 ? gmdate('Y-m-d\TH:i:s\Z', $segundos) : null;
    }

    if (!is_string($valor) || trim($valor) === '') {
        return null;
    }

    $utc = new DateTimeZone('UTC');
    foreach (['Y-m-d\TH:i:s\Z', DateTimeInterface::ATOM, 'Y-m-d H:i:s'] as $formato) {
        $fecha = DateTimeImmutable::createFromFormat($formato, trim($valor), $utc);
        if ($fecha !== false) {
            return $fecha->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');
        }
    }

    return null;
}

function saneador_moneda(mixed $valor, array $monedasValidas): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $limpio = strtoupper(trim($valor));
    return in_array($limpio, $monedasValidas, true) ? $limpio : null;
}

function saneador_codigo_http(mixed $valor): ?int
{
    $numero = saneador_numero($valor);
    if ($numero === null) {
        return null;
    }
    $codigo = (int) $numero;
    return ($codigo >= 100 && $codigo <= 599) ? $codigo : null;
}

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
