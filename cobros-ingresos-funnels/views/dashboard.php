<?php

use App\Config;

/** @var array $kpis */
/** @var float $carteraPendiente */
/** @var array $aging */
/** @var array $serieMensual */
/** @var array $funnelResumen */
/** @var array $topClientes */
/** @var int $meses */

$maxMensual = 1.0;
foreach ($serieMensual as $fila) {
    $maxMensual = max($maxMensual, (float) $fila['ingresos'], (float) $fila['cobros']);
}

$maxAging = max(1.0, ...array_values($aging));
$iconosAging = ['Al dia' => '●', '1-30 dias' => '▲', '31-60 dias' => '◆', '61+ dias' => '■'];
$coloresAging = ['Al dia' => 'var(--good)', '1-30 dias' => 'var(--warning)', '31-60 dias' => 'var(--serious)', '61+ dias' => 'var(--critical)'];

$etapasFunnel = [
    'Visitantes' => $funnelResumen['visitantes'],
    'Registrados' => $funnelResumen['registrados'],
    'Leads' => $funnelResumen['leads'],
    'Clientes' => $funnelResumen['clientes'],
];
$maxEtapa = max(1, ...array_values($etapasFunnel));
$rampaFunnel = ['var(--seq-250)', 'var(--seq-350)', 'var(--seq-450)', 'var(--seq-600)'];
?>

<h1><?= htmlspecialchars(Config::NOMBRE_SISTEMA) ?></h1>
<p class="subtitulo">Vista general de ingresos, cobros y conversion de usuarios a clientes.</p>

<form class="filtros" method="get">
    <input type="hidden" name="page" value="dashboard">
    <label for="meses">Periodo</label>
    <select name="meses" id="meses">
        <option value="3" <?= $meses === 3 ? 'selected' : '' ?>>Ultimos 3 meses</option>
        <option value="6" <?= $meses === 6 ? 'selected' : '' ?>>Ultimos 6 meses</option>
        <option value="12" <?= $meses === 12 ? 'selected' : '' ?>>Ultimos 12 meses</option>
    </select>
    <button type="submit">Aplicar</button>
</form>

<div class="grid grid-kpis">
    <div class="panel stat-tile">
        <span class="label">Facturado (periodo)</span>
        <span class="value"><?= Config::money($kpis['facturado']) ?></span>
        <span class="delta">Ingresos devengados</span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Cobrado (periodo)</span>
        <span class="value"><?= Config::money($kpis['cobrado']) ?></span>
        <span class="delta">Efectivo recibido</span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Tasa de cobranza</span>
        <span class="value"><?= number_format($kpis['tasa_cobranza'] * 100, 1) ?>%</span>
        <span class="delta">Cobrado / facturado del periodo</span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Cartera pendiente</span>
        <span class="value"><?= Config::money($carteraPendiente) ?></span>
        <span class="delta <?= $carteraPendiente > 0 ? 'critical' : 'good' ?>">Saldo abierto a hoy</span>
    </div>
</div>

<div class="grid grid-2">
    <div class="panel">
        <h2>Ingresos vs. cobros por mes</h2>
        <div class="legend">
            <span class="item"><span class="swatch" style="background:var(--series-1)"></span>Ingresos (facturado)</span>
            <span class="item"><span class="swatch" style="background:var(--series-2)"></span>Cobros (caja)</span>
        </div>
        <div class="chart">
            <?php foreach ($serieMensual as $fila): ?>
                <div class="grupo">
                    <div class="bar" style="height: <?= pct_altura((float) $fila['ingresos'], $maxMensual) ?>%; background: var(--series-1);"
                         tabindex="0" data-tooltip="Ingresos <?= mes_label($fila['mes']) ?>: <?= Config::money((float) $fila['ingresos']) ?>"></div>
                    <div class="bar" style="height: <?= pct_altura((float) $fila['cobros'], $maxMensual) ?>%; background: var(--series-2);"
                         tabindex="0" data-tooltip="Cobros <?= mes_label($fila['mes']) ?>: <?= Config::money((float) $fila['cobros']) ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-etiquetas">
            <?php foreach ($serieMensual as $fila): ?>
                <span class="grupo-label"><?= mes_label($fila['mes']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="panel">
        <h2>Cartera pendiente por antiguedad</h2>
        <div class="chart">
            <?php foreach ($aging as $bucket => $monto): ?>
                <div class="grupo">
                    <div class="bar" style="height: <?= pct_altura((float) $monto, $maxAging) ?>%; background: <?= $coloresAging[$bucket] ?>;"
                         tabindex="0" data-tooltip="<?= $bucket ?>: <?= Config::money((float) $monto) ?>"></div>
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

<div class="grid grid-2">
    <div class="panel">
        <h2>Funnel de conversion (periodo)</h2>
        <div class="chart">
            <?php foreach ($etapasFunnel as $etapa => $valor): $i = array_search($etapa, array_keys($etapasFunnel), true); ?>
                <div class="grupo">
                    <div class="bar" style="height: <?= pct_altura((float) $valor, $maxEtapa) ?>%; background: <?= $rampaFunnel[$i] ?>;"
                         tabindex="0" data-tooltip="<?= $etapa ?>: <?= $valor ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-etiquetas">
            <?php foreach ($etapasFunnel as $etapa => $valor): ?>
                <span class="grupo-label"><?= $etapa ?><br><strong><?= $valor ?></strong></span>
            <?php endforeach; ?>
        </div>
        <p class="subtitulo" style="margin-top:14px;"><a href="?page=funnel">Ver funnel completo &rarr;</a></p>
    </div>

    <div class="panel">
        <h2>Top clientes por facturacion</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Cliente</th><th>Segmento</th><th class="num">Facturado</th></tr>
                </thead>
                <tbody>
                <?php foreach ($topClientes as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['nombre']) ?></td>
                        <td><?= htmlspecialchars($c['segmento']) ?></td>
                        <td class="num"><?= Config::money((float) $c['total_facturado']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$topClientes): ?>
                    <tr><td colspan="3">Sin datos todavia.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
