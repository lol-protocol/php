<?php

use App\Config;

/** @var array $antiguedad  ['Al día' => float, '1-30 días' => float, '31-60 días' => float, '61+ días' => float], consolidado a USD */

$maxAntiguedad = max(1.0, ...array_values($antiguedad));
$iconosAntiguedad = ['Al día' => '●', '1-30 días' => '▲', '31-60 días' => '◆', '61+ días' => '■'];
$coloresAntiguedad = ['Al día' => 'var(--good)', '1-30 días' => 'var(--warning)', '31-60 días' => 'var(--serious)', '61+ días' => 'var(--critical)'];
?>
<div class="chart">
    <?php foreach ($antiguedad as $bucket => $monto): ?>
        <div class="grupo">
            <?= barra_svg(
                'bar',
                'height:' . altura_en_pct((float) $monto, $maxAntiguedad) . '%',
                $coloresAntiguedad[$bucket],
                $bucket . ': ' . Config::dinero((float) $monto)
            ) ?>
        </div>
    <?php endforeach; ?>
</div>
<div class="chart-etiquetas">
    <?php foreach ($antiguedad as $bucket => $monto): ?>
        <span class="grupo-label"><?= $iconosAntiguedad[$bucket] ?> <?= $bucket ?></span>
    <?php endforeach; ?>
</div>
