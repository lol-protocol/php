<?php

/** @var list<array{tipo: string, texto: string}> $avisos */
/** @var array<string, string> $monedas */
/** @var string $moneda */
/** @var list<array{codigo: string, nombre: string, tasa_a_usd: string, cotizada_en: ?string, tasa_antes: ?string}> $filas */
/** @var int $dias */
/** @var array{ultima: ?DateTimeImmutable, fuente: ?string, pendientes: list<string>} $tasas */
?>

<h1>Tasas de cambio</h1>
<p class="subtitulo">La tasa de hoy de cada moneda y cuánto cambió en <?= $dias ?> días. «Valor en USD» es lo que vale una unidad de la moneda, y el cambio es el de ese valor: positivo quiere decir que la moneda se fortaleció frente al dólar. Las tasas se guardan desde que existe esta pantalla; lo anterior no se puede recuperar, y hasta que pasen <?= $dias ?> días no hay con qué comparar.</p>
<?php include __DIR__ . '/../_tasas_de_cambio.php'; ?>
<?php include __DIR__ . '/_selector.php'; ?>
<?php include __DIR__ . '/../_avisos.php'; ?>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Moneda</th><th class="num">1 USD =</th><th class="num">Valor en USD</th><th class="num">Cambio en <?= $dias ?> días</th><th>Cotización</th></tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $f): ?>
                <?php $cambio = $f['tasa_antes'] === null ? null : variacion_pct((float) $f['tasa_a_usd'], (float) $f['tasa_antes']); ?>
                <tr>
                    <td><a href="<?= e(url_con_parametro('moneda', $f['codigo'])) ?>"><?= e($f['codigo']) ?></a> <span class="atenuado"><?= e($f['nombre']) ?></span></td>
                    <td class="num"><?= e(unidades_por_usd($f['tasa_a_usd'])) ?></td>
                    <td class="num"><?= e(tasa_en_usd($f['tasa_a_usd'])) ?></td>
                    <td class="num"><?= $cambio === null ? '—' : e(($cambio >= 0 ? '+' : '') . number_format($cambio, 2) . '%') ?></td>
                    <td><?= $f['cotizada_en'] === null ? '<span class="atenuado">de ejemplo</span>' : e(substr($f['cotizada_en'], 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
