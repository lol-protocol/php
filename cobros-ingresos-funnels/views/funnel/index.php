<?php

use App\Etiquetas;

/** @var array $resumen */
/** @var array $porCanal */
/** @var array $porPais */
/** @var array $porGenero */
/** @var array $porRangoEdad */
/** @var array $serieMensual */
/** @var float $tiempoPromedioConversion */
/** @var int $meses */
/** @var string $desde */
/** @var string $hasta */
/** @var bool $personalizado */

$etapas = [
    'Visitantes' => $resumen['visitantes'],
    'Registrados' => $resumen['registrados'],
    'Leads' => $resumen['leads'],
    'Clientes' => $resumen['clientes'],
];
$maxEtapa = max(1, ...array_values($etapas));
$rampaFunnel = ['var(--seq-250)', 'var(--seq-350)', 'var(--seq-450)', 'var(--seq-600)'];

$tasaGlobal = $resumen['visitantes'] > 0 ? $resumen['clientes'] / $resumen['visitantes'] * 100 : 0.0;
?>

<h1>Funnel de conversión</h1>
<p class="subtitulo">De visitante a cliente: dónde se pierden usuarios y qué tan rápido convierten.</p>

<form class="filtros" method="get">
    <input type="hidden" name="page" value="funnel">
    <?php include __DIR__ . '/../_filtro_fechas.php'; ?>
    <button type="submit">Aplicar</button>
</form>
<?php include __DIR__ . '/../_avisos.php'; ?>

<div class="grid grid-kpis">
    <div class="panel stat-tile">
        <span class="label">Visitantes (período)</span>
        <span class="value"><?= $resumen['visitantes'] ?></span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Clientes nuevos</span>
        <span class="value"><?= $resumen['clientes'] ?></span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Conversión global</span>
        <span class="value"><?= number_format($tasaGlobal, 1) ?>%</span>
        <span class="delta">Visitante &rarr; cliente</span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Tiempo promedio de conversión</span>
        <span class="value"><?= number_format($tiempoPromedioConversion, 1) ?> días</span>
    </div>
</div>

<div class="panel">
    <h2>Embudo por etapa</h2>
    <div class="chart">
        <?php $i = 0; foreach ($etapas as $etapa => $valor): ?>
            <div class="grupo">
                <?php $pct = $resumen['visitantes'] > 0 ? number_format($valor / $resumen['visitantes'] * 100, 1) : 0; ?>
                <?= svg_barra('bar', 'height:' . pct_altura((float) $valor, $maxEtapa) . '%', $rampaFunnel[$i], "{$etapa}: {$valor} ({$pct}% de visitantes)") ?>
            </div>
            <?php $i++; ?>
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
        <?php
        $filas = $serieMensual;
        $series = [
            ['clave' => 'visitantes', 'etiqueta' => 'Visitantes', 'color' => 'var(--series-1)'],
            ['clave' => 'clientes', 'etiqueta' => 'Clientes', 'color' => 'var(--series-2)'],
        ];
        $formato = 'entero';
        include __DIR__ . '/../_grafico_serie_mensual.php';
        ?>
    </div>

    <div class="panel">
        <h2>Conversión por canal de adquisición</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Canal</th><th class="num">Visitantes</th><th class="num">Registrados</th><th class="num">Leads</th><th class="num">Clientes</th><th class="num">Conversión</th></tr>
                </thead>
                <tbody>
                <?php foreach ($porCanal as $c): $tasa = $c['visitantes'] > 0 ? $c['clientes'] / $c['visitantes'] * 100 : 0; ?>
                    <tr>
                        <td><?= htmlspecialchars(Etiquetas::canal($c['canal'])) ?></td>
                        <td class="num"><?= $c['visitantes'] ?></td>
                        <td class="num"><?= $c['registrados'] ?></td>
                        <td class="num"><?= $c['leads'] ?></td>
                        <td class="num"><?= $c['clientes'] ?></td>
                        <td class="num"><?= number_format($tasa, 1) ?>%</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$porCanal): ?>
                    <tr><td colspan="6">Sin datos para este período.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <?php $titulo = 'Conversión por país'; $filas = $porPais; include __DIR__ . '/_tabla_dimension.php'; ?>
    <?php $titulo = 'Conversión por género'; $filas = $porGenero; include __DIR__ . '/_tabla_dimension.php'; ?>
</div>
<div class="grid grid-2">
    <?php $titulo = 'Conversión por rango de edad'; $filas = $porRangoEdad; include __DIR__ . '/_tabla_dimension.php'; ?>
</div>
