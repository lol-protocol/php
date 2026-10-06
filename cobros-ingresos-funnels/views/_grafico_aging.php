<?php

use App\Config;

/** @var array $aging  ['Al día' => float, '1-30 días' => float, '31-60 días' => float, '61+ días' => float], consolidado a USD */

$maxAging = max(1.0, ...array_values($aging));
$iconosAging = ['Al día' => '●', '1-30 días' => '▲', '31-60 días' => '◆', '61+ días' => '■'];
$coloresAging = ['Al día' => 'var(--good)', '1-30 días' => 'var(--warning)', '31-60 días' => 'var(--serious)', '61+ días' => 'var(--critical)'];
?>
<div class="chart">
    <?php foreach ($aging as $bucket => $monto): ?>
        <div class="grupo">
            <?= svg_barra(
                'bar',
                'height:' . pct_altura((float) $monto, $maxAging) . '%',
                $coloresAging[$bucket],
                $bucket . ': ' . Config::money((float) $monto)
            ) ?>
        </div>
    <?php endforeach; ?>
</div>
<div class="chart-etiquetas">
    <?php foreach ($aging as $bucket => $monto): ?>
        <span class="grupo-label"><?= $iconosAging[$bucket] ?> <?= $bucket ?></span>
    <?php endforeach; ?>
</div>
