<?php

/** @var list<array{tipo: string, texto: string}> $avisos */
/** @var array<string, string> $monedas */
/** @var string $moneda */
/** @var list<array{id: int|string, tasa_a_usd: string, cotizada_en: ?string, fuente: ?string, registrada_en: string, tasa_anterior: ?string}> $filas */
/** @var int $total */
/** @var int $pagina */
/** @var int $totalPaginas */
/** @var array{ultima: ?DateTimeImmutable, fuente: ?string, pendientes: list<string>} $tasas */
?>

<h1>Tasas de cambio · <?= e($moneda) ?></h1>
<p class="subtitulo">Historial de <?= e($monedas[$moneda]) ?>: una fila cada vez que cambió su tasa, la fecha de su cotización o su fuente, de la más reciente a la más antigua. El cambio es el del valor de la moneda en dólares frente a la fila anterior; entre una tasa de ejemplo y una real no se calcula.</p>
<?php include __DIR__ . '/../_tasas_de_cambio.php'; ?>
<?php include __DIR__ . '/_selector.php'; ?>
<?php include __DIR__ . '/../_avisos.php'; ?>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Cotización</th><th class="num">1 USD =</th><th class="num">Valor en USD</th><th class="num">Cambio</th><th>Fuente</th><th>Registrada</th></tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $f): ?>
                <?php $cambio = $f['tasa_anterior'] === null ? null : variacion_pct((float) $f['tasa_a_usd'], (float) $f['tasa_anterior']); ?>
                <tr>
                    <td><?= $f['cotizada_en'] === null ? '<span class="atenuado">de ejemplo</span>' : e(substr($f['cotizada_en'], 0, 16)) ?></td>
                    <td class="num"><?= e(unidades_por_usd($f['tasa_a_usd'])) ?></td>
                    <td class="num"><?= e(tasa_en_usd($f['tasa_a_usd'])) ?></td>
                    <td class="num"><?= $cambio === null ? '—' : e(($cambio >= 0 ? '+' : '') . number_format($cambio, 2) . '%') ?></td>
                    <td><?= $f['fuente'] === null ? '—' : e($f['fuente']) ?></td>
                    <td><?= e(substr($f['registrada_en'], 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../_paginacion.php'; ?>
</div>
