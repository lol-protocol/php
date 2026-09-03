<?php

declare(strict_types=1);

/**
 * Primitivas de saneamiento: cada una limpia/valida un valor suelto (texto, id,
 * tipo, número, moneda, código HTTP). Sin conocimiento del esquema de "acción".
 */

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

function saneador_ip(mixed $valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    // Admite un ":puerto" colado ("203.45.12.9:54321") antes de validar.
    $limpio = preg_replace('/:\d+$/', '', trim($valor)) ?? '';
    return filter_var($limpio, FILTER_VALIDATE_IP) !== false ? $limpio : null;
}

function saneador_codigo_pais(mixed $valor, array $paisesValidos): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $limpio = strtoupper(trim($valor));
    return in_array($limpio, $paisesValidos, true) ? $limpio : null;
}
