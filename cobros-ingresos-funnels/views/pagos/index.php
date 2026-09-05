<?php

use App\Config;

/** @var array $cobrosPorMes */
/** @var array $porMetodo */
/** @var array $pagos */
/** @var int $totalPagos */
/** @var int $totalPaginas */
/** @var int $pagina */
/** @var int $meses */
/** @var string $desde */
/** @var string $hasta */
/** @var bool $personalizado */
/** @var string $cliente */

$maxCobros = 1.0;
foreach ($cobrosPorMes as $fila) {
    $maxCobros = max($maxCobros, (float) $fila['total']);
}

$coloresMetodo = ['transferencia' => 'var(--series-1)', 'tarjeta' => 'var(--series-2)', 'efectivo' => 'var(--seq-350)'];
$metodoLabel = ['transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'efectivo' => 'Efectivo'];
$maxMetodo = 1.0;
foreach ($porMetodo as $fila) {
    $maxMetodo = max($maxMetodo, (float) $fila['total']);
}
$totalPeriodo = array_sum(array_column($porMetodo, 'total'));
?>

<h1>Pagos</h1>
<p class="subtitulo">Efectivo cobrado: cuando entra el dinero y por que medio.</p>

<form class="filtros" method="get">
    <input type="hidden" name="page" value="pagos">
    <label for="meses">Periodo</label>
    <select name="meses" id="meses">
        <option value="3" <?= $meses === 3 ? 'selected' : '' ?>>Ultimos 3 meses</option>
        <option value="6" <?= $meses === 6 ? 'selected' : '' ?>>Ultimos 6 meses</option>
        <option value="12" <?= $meses === 12 ? 'selected' : '' ?>>Ultimos 12 meses</option>
    </select>
    <?php include __DIR__ . '/../_filtro_fechas.php'; ?>
    <label for="cliente">Cliente</label>
    <input type="search" name="cliente" id="cliente" placeholder="Buscar por nombre..." value="<?= htmlspecialchars($cliente) ?>">
    <button type="submit">Aplicar</button>
    <a href="?page=pago-nuevo" style="margin-left:auto;">+ Nuevo pago</a>
</form>

<div class="grid grid-kpis">
    <div class="panel stat-tile">
        <span class="label">Total cobrado (periodo, USD)</span>
        <span class="value"><?= Config::money($totalPeriodo) ?></span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Pagos registrados</span>
        <span class="value"><?= $totalPagos ?></span>
    </div>
</div>

<div class="grid grid-2">
    <div class="panel">
        <h2>Cobros por mes</h2>
        <div class="chart">
            <?php foreach ($cobrosPorMes as $fila): ?>
                <div class="grupo">
                    <?= svg_barra(
                        'bar',
                        'height:' . pct_altura((float) $fila['total'], $maxCobros) . '%',
                        'var(--series-2)',
                        mes_label($fila['mes']) . ': ' . Config::money((float) $fila['total'])
                    ) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-etiquetas">
            <?php foreach ($cobrosPorMes as $fila): ?>
                <span class="grupo-label"><?= mes_label($fila['mes']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="panel">
        <h2>Por metodo de pago</h2>
        <?php foreach ($porMetodo as $fila): ?>
            <div class="hbar-row">
                <span class="hbar-label"><?= $metodoLabel[$fila['metodo']] ?? htmlspecialchars($fila['metodo']) ?></span>
                <span class="hbar-track">
                    <?= svg_barra(
                        'hbar-fill',
                        'width:' . pct_altura((float) $fila['total'], $maxMetodo) . '%',
                        $coloresMetodo[$fila['metodo']] ?? 'var(--series-1)',
                        ($metodoLabel[$fila['metodo']] ?? $fila['metodo']) . ': ' . Config::money((float) $fila['total']) . ' (' . $fila['cantidad'] . ')',
                        3
                    ) ?>
                </span>
                <span class="hbar-value"><?= Config::money((float) $fila['total']) ?> (<?= $fila['cantidad'] ?>)</span>
            </div>
        <?php endforeach; ?>
        <?php if (!$porMetodo): ?>
            <p class="subtitulo">Sin pagos en este periodo.</p>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <h2>Detalle de pagos (<?= $totalPagos ?>)</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Cliente</th><th>Fecha</th><th>Metodo</th><th>Origen</th><th class="num">Monto</th><th>&nbsp;</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pagos as $p): ?>
                <tr>
                    <td><a href="?page=cliente&id=<?= (int) $p['cliente_id'] ?>"><?= htmlspecialchars($p['cliente']) ?></a></td>
                    <td><?= htmlspecialchars($p['fecha_pago']) ?></td>
                    <td><?= $metodoLabel[$p['metodo']] ?? htmlspecialchars($p['metodo']) ?></td>
                    <td><?= $p['boleta_id'] ? 'Boleta #' . (int) $p['boleta_id'] : 'Anticipo' ?></td>
                    <td class="num"><?= money_moneda((float) $p['monto'], $p['moneda_codigo']) ?></td>
                    <td class="acciones">
                        <?php if ($p['anulada']): ?>
                            <span class="badge anulada">Anulado</span>
                        <?php else: ?>
                            <a href="?page=pago-editar&id=<?= (int) $p['id'] ?>">Editar</a>
                            <a href="?page=pago-anular&id=<?= (int) $p['id'] ?>">Anular</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pagos): ?>
                <tr><td colspan="6">No hay pagos para este filtro.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../_paginacion.php'; ?>
</div>
