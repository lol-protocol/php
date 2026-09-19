<?php

declare(strict_types=1);

require_once __DIR__ . '/ayudantes.php';

/**
 * Generadores de valores "crudos": misma información, en uno de varios formatos
 * inconsistentes posibles — lo que el saneador (saneador.php) después normaliza.
 */

function crudo_marca_temporal(int $segundosEpoch): int|string
{
    switch (mt_rand(1, 5)) {
        case 1:
            return gmdate('Y-m-d\TH:i:s\Z', $segundosEpoch);
        case 2:
            $offsets = [-5, -3, 0, 1, 2, 9];
            $offset = $offsets[array_rand($offsets)];
            $signo = $offset >= 0 ? '+' : '-';
            $horas = str_pad((string) abs($offset), 2, '0', STR_PAD_LEFT);
            return gmdate('Y-m-d\TH:i:s', $segundosEpoch + $offset * 3600) . $signo . $horas . ':00';
        case 3:
            return gmdate('Y-m-d H:i:s', $segundosEpoch); // estilo SQL, sin zona explícita
        case 4:
            return $segundosEpoch; // epoch en segundos
        default:
            return $segundosEpoch * 1000; // epoch en milisegundos
    }
}

function crudo_numero(float $valor, int $decimales = 0): string|float
{
    return match (mt_rand(1, 4)) {
        1 => round($valor, $decimales),
        2 => (string) round($valor, $decimales),
        3 => '$' . number_format($valor, max($decimales, 2)),
        default => ' ' . round($valor, $decimales) . ' ',
    };
}

function crudo_may_min(string $s): string
{
    return match (mt_rand(1, 3)) {
        1 => strtoupper($s),
        2 => strtolower($s),
        default => $s,
    };
}

function crudo_espacios(string $s): string
{
    return tal_vez(20) ? ("  " . $s . "\n") : $s;
}

function crudo_comentario(array $base, array $inseguros): string
{
    $texto = $base[array_rand($base)];
    if (tal_vez(15)) {
        $texto .= ' ' . $inseguros[array_rand($inseguros)];
    }
    return crudo_espacios($texto);
}
