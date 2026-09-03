<?php

declare(strict_types=1);

/** Convierte 'YYYY-MM' a una etiqueta corta en espanol, ej. 'mar 2026'. */
function mes_label(string $ym): string
{
    static $meses = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr',
        '05' => 'may', '06' => 'jun', '07' => 'jul', '08' => 'ago',
        '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic'];
    [$anio, $mes] = explode('-', $ym);
    return ($meses[$mes] ?? $mes) . ' ' . $anio;
}

/** Altura porcentual para una barra, con un piso visible cuando el valor es > 0. */
function pct_altura(float $valor, float $max): float
{
    if ($max <= 0 || $valor <= 0) {
        return 0.0;
    }
    return max(2.0, round($valor / $max * 100, 1));
}
