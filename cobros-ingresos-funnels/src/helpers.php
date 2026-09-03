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

/** Formato compacto para espacios angostos, ej. '$12.3K', '$4.2M'. */
function money_compacta(float $valor): string
{
    $moneda = \App\Config::MONEDA;
    $abs = abs($valor);
    if ($abs >= 1_000_000) {
        return $moneda . number_format($abs / 1_000_000, 1) . 'M';
    }
    if ($abs >= 1_000) {
        return $moneda . number_format($abs / 1_000, 1) . 'K';
    }
    return \App\Config::money($valor);
}

/** Altura porcentual para una barra, con un piso visible cuando el valor es > 0. */
function pct_altura(float $valor, float $max): float
{
    if ($max <= 0 || $valor <= 0) {
        return 0.0;
    }
    return max(2.0, round($valor / $max * 100, 1));
}
