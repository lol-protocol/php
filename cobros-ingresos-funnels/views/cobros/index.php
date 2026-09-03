<?php

use App\Config;

/** @var array $kpis */
/** @var float $carteraPendiente */
/** @var array $aging */
/** @var array $ingresosPorMes */
/** @var array $boletas */
/** @var int $meses */
/** @var string $estado */

$maxIngresos = 1.0;
foreach ($ingresosPorMes as $fila) {
    $maxIngresos = max($maxIngresos, (float) $fila['total']);
}

$maxAging = max(1.0, ...array_values($aging));
$iconosAging = ['Al dia' => '●', '1-30 dias' => '▲', '31-60 dias' => '◆', '61+ dias' => '■'];
$coloresAging = ['Al dia' => 'var(--good)', '1-30 dias' => 'var(--warning)', '31-60 dias' => 'var(--serious)', '61+ dias' => 'var(--critical)'];

$estadosLabel = ['pagada' => 'Pagada', 'pendiente' => 'Pendiente', 'parcial' => 'Parcial', 'vencida' => 'Vencida'];
?>

<h1>Cobros e ingresos</h1>
<p class="subtitulo">Ingresos devengados (boletas emitidas) frente al efectivo realmente cobrado, y estado de la cartera.</p>

<form class="filtros" method="get">
    <input type="hidden" name="page" value="cobros">
    <label for="meses">Periodo</label>
    <select name="meses" id="meses">
        <option value="3" <?= $meses === 3 ? 'selected' : '' ?>>Ultimos 3 meses</option>
        <option value="6" <?= $meses === 6 ? 'selected' : '' ?>>Ultimos 6 meses</option>
        <option value="12" <?= $meses === 12 ? 'selected' : '' ?>>Ultimos 12 meses</option>
    </select>
    <label for="estado">Estado</label>
    <select name="estado" id="estado">
        <option value="">Todos</option>
        <?php foreach ($estadosLabel as $clave => $etiqueta): ?>
            <option value="<?= $clave ?>" <?= $estado === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Aplicar</button>
</form>

<div class="grid grid-kpis">
    <div class="panel stat-tile">
        <span class="label">Facturado (periodo)</span>
        <span class="value"><?= Config::money($kpis['facturado']) ?></span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Cobrado (periodo)</span>
        <span class="value"><?= Config::money($kpis['cobrado']) ?></span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Tasa de cobranza</span>
        <span class="value"><?= number_format($kpis['tasa_cobranza'] * 100, 1) ?>%</span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Cartera pendiente (a hoy)</span>
        <span class="value"><?= Config::money($carteraPendiente) ?></span>
    </div>
</div>

<div class="grid grid-2">
    <div class="panel">
        <h2>Ingresos facturados por mes</h2>
        <div class="chart">
            <?php foreach ($ingresosPorMes as $fila): ?>
                <div class="grupo">
                    <?= svg_barra(
                        'bar',
                        'height:' . pct_altura((float) $fila['total'], $maxIngresos) . '%',
                        'var(--series-1)',
                        mes_label($fila['mes']) . ': ' . Config::money((float) $fila['total'])
                    ) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-etiquetas">
            <?php foreach ($ingresosPorMes as $fila): ?>
                <span class="grupo-label"><?= mes_label($fila['mes']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="panel">
        <h2>Cartera pendiente por antiguedad</h2>
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
    </div>
</div>

<div class="panel">
    <h2>Boletas (<?= count($boletas) ?>)</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Cliente</th><th>Concepto</th><th>Emision</th><th>Vencimiento</th>
                <th class="num">Monto</th><th class="num">Saldo</th><th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($boletas as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['cliente']) ?></td>
                    <td><?= htmlspecialchars($b['concepto']) ?></td>
                    <td><?= htmlspecialchars($b['fecha_emision']) ?></td>
                    <td><?= htmlspecialchars($b['fecha_vencimiento']) ?></td>
                    <td class="num"><?= Config::money((float) $b['monto']) ?></td>
                    <td class="num"><?= Config::money((float) $b['saldo']) ?></td>
                    <td><span class="badge <?= $b['estado'] ?>"><?= $estadosLabel[$b['estado']] ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$boletas): ?>
                <tr><td colspan="7">No hay boletas para este filtro.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
