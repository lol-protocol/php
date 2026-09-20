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

// Se escala por valor absoluto: un mes puede dar negativo (mas devoluciones
// que cobros) y si se lo midiera con el valor crudo la barra quedaria en 0%,
// o sea invisible, igual que un mes sin movimiento.
$maxValor = 1.0;
foreach ($filas as $fila) {
    foreach ($series as $serie) {
        $maxValor = max($maxValor, abs((float) $fila[$serie['clave']]));
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
                <?php $valor = (float) $fila[$serie['clave']]; ?>
                <?= svg_barra(
                    'bar',
                    'height:' . pct_altura(abs($valor), $maxValor) . '%',
                    $valor < 0 ? 'var(--critical)' : $serie['color'],
                    ($serie['etiqueta'] !== '' ? $serie['etiqueta'] . ' ' : '')
                        . mes_label($fila['mes']) . ': ' . $formatearValor($valor)
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
