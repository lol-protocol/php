<?php

declare(strict_types=1);

/**
 * Saneamiento de fecha/hora y duración. Formato canónico de salida: Y.m.d.H.i.s
 * (ej. "2026.09.03.19.47.10", siempre UTC) — mismo orden que ISO-8601, así que
 * sigue ordenando bien como texto, solo que con puntos en vez de guiones/T/Z.
 */

const SANEADOR_FORMATO_FECHA = 'Y.m.d.H.i.s';

function saneador_marca_temporal(mixed $valor): ?string
{
    if (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric($valor))) {
        $numero = (float) $valor;
        // Heurística estándar para distinguir epoch en segundos de epoch en milisegundos.
        $segundos = $numero > 10_000_000_000 ? (int) round($numero / 1000) : (int) round($numero);
        return $segundos > 0 ? gmdate(SANEADOR_FORMATO_FECHA, $segundos) : null;
    }

    if (!is_string($valor) || trim($valor) === '') {
        return null;
    }

    $utc = new DateTimeZone('UTC');
    foreach (['Y-m-d\TH:i:s\Z', DateTimeInterface::ATOM, 'Y-m-d H:i:s'] as $formato) {
        $fecha = DateTimeImmutable::createFromFormat($formato, trim($valor), $utc);
        if ($fecha !== false) {
            return $fecha->setTimezone($utc)->format(SANEADOR_FORMATO_FECHA);
        }
    }

    return null;
}

function saneador_duracion_ms(mixed $valor): ?int
{
    $numero = saneador_numero($valor);
    if ($numero === null || $numero <= 0 || $numero > 3_600_000) {
        return null; // duración inválida, negativa o mayor a 1 hora: no es recuperable
    }
    return (int) round($numero);
}
