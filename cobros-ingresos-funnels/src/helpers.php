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

/**
 * Mini-grafico de barra individual en SVG (vertical u horizontal segun el
 * $estilo que se le pase: 'height:NN%' o 'width:NN%'). No lleva viewBox a
 * proposito: sin el, las coordenadas del rect son px reales del layout CSS
 * que la contiene (igual que un div con border-radius), asi que el tamano
 * lo sigue resolviendo el flexbox existente sin distorsion.
 */
function svg_barra(string $clase, string $estilo, string $color, string $tooltip, int $radio = 4): string
{
    $tituloEsc = htmlspecialchars($tooltip, ENT_QUOTES);
    return '<svg class="' . htmlspecialchars($clase, ENT_QUOTES) . '" style="' . htmlspecialchars($estilo, ENT_QUOTES) . '"'
        . ' role="img" aria-label="' . $tituloEsc . '" tabindex="0">'
        . '<rect width="100%" height="100%" rx="' . $radio . '" ry="' . $radio . '" fill="' . htmlspecialchars($color, ENT_QUOTES) . '"></rect>'
        . '<title>' . $tituloEsc . '</title>'
        . '</svg>';
}
