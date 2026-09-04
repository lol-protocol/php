<?php

/** @var array $resumen */
/** @var array $porCanal */
/** @var array $porPais */
/** @var array $porGenero */
/** @var array $porRangoEdad */
/** @var array $serieMensual */
/** @var float $tiempoPromedioConversion */
/** @var int $meses */

$etapas = [
    'Visitantes' => $resumen['visitantes'],
    'Registrados' => $resumen['registrados'],
    'Leads' => $resumen['leads'],
    'Clientes' => $resumen['clientes'],
];
$maxEtapa = max(1, ...array_values($etapas));
$rampaFunnel = ['var(--seq-250)', 'var(--seq-350)', 'var(--seq-450)', 'var(--seq-600)'];
$canalLabel = ['organico' => 'Organico', 'ads' => 'Ads', 'referido' => 'Referido', 'redes_sociales' => 'Redes sociales', 'email' => 'Email'];

$tasaGlobal = $resumen['visitantes'] > 0 ? $resumen['clientes'] / $resumen['visitantes'] * 100 : 0.0;

$maxSerie = 1.0;
foreach ($serieMensual as $fila) {
    $maxSerie = max($maxSerie, (float) $fila['visitantes'], (float) $fila['clientes']);
}
?>

<h1>Funnel de conversion</h1>
<p class="subtitulo">De visitante a cliente: donde se pierden usuarios y que tan rapido convierten.</p>

<form class="filtros" method="get">
    <input type="hidden" name="page" value="funnel">
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
        <span class="label">Visitantes (periodo)</span>
        <span class="value"><?= $resumen['visitantes'] ?></span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Clientes nuevos</span>
        <span class="value"><?= $resumen['clientes'] ?></span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Conversion global</span>
        <span class="value"><?= number_format($tasaGlobal, 1) ?>%</span>
        <span class="delta">Visitante &rarr; cliente</span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Tiempo promedio de conversion</span>
        <span class="value"><?= number_format($tiempoPromedioConversion, 1) ?> dias</span>
    </div>
</div>

<div class="panel">
    <h2>Embudo por etapa</h2>
    <div class="chart">
        <?php foreach ($etapas as $etapa => $valor): $i = array_search($etapa, array_keys($etapas), true); ?>
            <div class="grupo">
                <?php $pct = $resumen['visitantes'] > 0 ? number_format($valor / $resumen['visitantes'] * 100, 1) : 0; ?>
                <?= svg_barra('bar', 'height:' . pct_altura((float) $valor, $maxEtapa) . '%', $rampaFunnel[$i], "{$etapa}: {$valor} ({$pct}% de visitantes)") ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="chart-etiquetas">
        <?php foreach ($etapas as $etapa => $valor): ?>
            <span class="grupo-label">
                <?= $etapa ?><br>
                <strong><?= $valor ?></strong><br>
                <?= $resumen['visitantes'] > 0 ? number_format($valor / $resumen['visitantes'] * 100, 1) : 0 ?>%
            </span>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid grid-2">
    <div class="panel">
        <h2>Visitantes vs. clientes por mes</h2>
        <div class="legend">
            <span class="item"><span class="swatch" style="background:var(--series-1)"></span>Visitantes</span>
            <span class="item"><span class="swatch" style="background:var(--series-2)"></span>Clientes nuevos</span>
        </div>
        <div class="chart">
            <?php foreach ($serieMensual as $fila): ?>
                <div class="grupo">
                    <?= svg_barra(
                        'bar',
                        'height:' . pct_altura((float) $fila['visitantes'], $maxSerie) . '%',
                        'var(--series-1)',
                        'Visitantes ' . mes_label($fila['mes']) . ': ' . $fila['visitantes']
                    ) ?>
                    <?= svg_barra(
                        'bar',
                        'height:' . pct_altura((float) $fila['clientes'], $maxSerie) . '%',
                        'var(--series-2)',
                        'Clientes ' . mes_label($fila['mes']) . ': ' . $fila['clientes']
                    ) ?>
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
        <h2>Conversion por canal de adquisicion</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Canal</th><th class="num">Visitantes</th><th class="num">Registrados</th><th class="num">Leads</th><th class="num">Clientes</th><th class="num">Conversion</th></tr>
                </thead>
                <tbody>
                <?php foreach ($porCanal as $c): $tasa = $c['visitantes'] > 0 ? $c['clientes'] / $c['visitantes'] * 100 : 0; ?>
                    <tr>
                        <td><?= $canalLabel[$c['canal']] ?? htmlspecialchars($c['canal']) ?></td>
                        <td class="num"><?= $c['visitantes'] ?></td>
                        <td class="num"><?= $c['registrados'] ?></td>
                        <td class="num"><?= $c['leads'] ?></td>
                        <td class="num"><?= $c['clientes'] ?></td>
                        <td class="num"><?= number_format($tasa, 1) ?>%</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$porCanal): ?>
                    <tr><td colspan="6">Sin datos para este periodo.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <?php $titulo = 'Conversion por pais'; $filas = $porPais; include __DIR__ . '/_tabla_dimension.php'; ?>
    <?php $titulo = 'Conversion por genero'; $filas = $porGenero; include __DIR__ . '/_tabla_dimension.php'; ?>
</div>
<div class="grid grid-2">
    <?php $titulo = 'Conversion por rango de edad'; $filas = $porRangoEdad; include __DIR__ . '/_tabla_dimension.php'; ?>
</div>
