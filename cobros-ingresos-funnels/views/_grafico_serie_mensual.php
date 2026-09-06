<?php

use App\Config;

/**
 * Grafico de barras verticales por mes, con 1 o mas series superpuestas en
 * cada mes (ej. ingresos vs. cobros). Usado por dashboard, cobros, pagos y
 * funnel para no repetir el mismo par de foreach + svg_barra en cada uno.
 *
 * @var array  $filas   filas con clave 'mes' (YYYY-MM) + una clave numerica por serie
 * @var array  $series  lista de series: cada una ['clave' => string (la clave en $filas),
 *                       'etiqueta' => string (prefijo del tooltip; '' si hay una sola serie),
 *                       'color' => string (css var)]
 * @var string $formato 'money' (Config::money) o 'entero' (numero tal cual), para el tooltip
 */

$maxValor = 1.0;
foreach ($filas as $fila) {
    foreach ($series as $serie) {
        $maxValor = max($maxValor, (float) $fila[$serie['clave']]);
    }
}

$formatearValor = $formato === 'money'
    ? static fn (float $v): string => Config::money($v)
    : static fn (float $v): string => (string) $v;
?>
<div class="chart">
    <?php foreach ($filas as $fila): ?>
        <div class="grupo">
            <?php foreach ($series as $serie): ?>
                <?= svg_barra(
                    'bar',
                    'height:' . pct_altura((float) $fila[$serie['clave']], $maxValor) . '%',
                    $serie['color'],
                    ($serie['etiqueta'] !== '' ? $serie['etiqueta'] . ' ' : '')
                        . mes_label($fila['mes']) . ': ' . $formatearValor((float) $fila[$serie['clave']])
                ) ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
<div class="chart-etiquetas">
    <?php foreach ($filas as $fila): ?>
        <span class="grupo-label"><?= mes_label($fila['mes']) ?></span>
    <?php endforeach; ?>
</div>
