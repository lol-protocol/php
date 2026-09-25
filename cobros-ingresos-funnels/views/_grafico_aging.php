<?php

use App\Config;

/** @var array $aging  ['Al dia' => float, '1-30 dias' => float, '31-60 dias' => float, '61+ dias' => float], consolidado a USD */

$maxAging = max(1.0, ...array_values($aging));
$iconosAging = ['Al dia' => '●', '1-30 dias' => '▲', '31-60 dias' => '◆', '61+ dias' => '■'];
$coloresAging = ['Al dia' => 'var(--good)', '1-30 dias' => 'var(--warning)', '31-60 dias' => 'var(--serious)', '61+ dias' => 'var(--critical)'];
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
