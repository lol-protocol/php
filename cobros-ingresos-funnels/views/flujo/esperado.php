<?php

use App\Config;
use App\Repositories\FlujoDeCajaRepository;

/** @var list<array{tipo: string, texto: string}> $avisos */
/** @var int $meses */
/** @var list<array{clave: string, boletas: int, clientes: int, saldo_usd: float}> $cubetas */
/** @var array{total_usd: float, vencido_usd: float, este_mes_usd: float, boletas: int} $totales */
/** @var array{ultima: ?DateTimeImmutable, fuente: ?string, pendientes: list<string>} $tasas */

$etiqueta = static fn (string $clave): string => match ($clave) {
    FlujoDeCajaRepository::VENCIDO => 'Ya vencido',
    FlujoDeCajaRepository::DESPUES => 'Más adelante',
    default => etiqueta_de_mes($clave),
};
$color = static fn (string $clave): string => match ($clave) {
    FlujoDeCajaRepository::VENCIDO => 'var(--critical)',
    FlujoDeCajaRepository::DESPUES => 'var(--text-muted)',
    default => 'var(--series-1)',
};
$maximo = max(1.0, ...array_column($cubetas, 'saldo_usd'));
?>

<h1>Flujo de caja esperado</h1>
<p class="subtitulo">Lo que falta cobrar de las boletas abiertas, por el mes en que vence cada una, en USD a la tasa de hoy. Suma lo mismo que la cartera pendiente del Dashboard. Supone que cada cliente paga el día del vencimiento: no es una predicción de cuándo van a pagar; lo que ya venció va aparte.</p>
<?php include __DIR__ . '/../_tasas_de_cambio.php'; ?>

<form class="filtros" method="get">
    <input type="hidden" name="page" value="flujo">
    <label for="meses">Horizonte</label>
    <select name="meses" id="meses">
        <?php foreach (FlujoDeCajaRepository::HORIZONTES as $clave => $texto): ?>
            <option value="<?= $clave ?>" <?= $meses === $clave ? 'selected' : '' ?>><?= e($texto) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Aplicar</button>
</form>
<?php include __DIR__ . '/../_avisos.php'; ?>

<div class="grid grid-kpis">
    <div class="panel stat-tile">
        <span class="label">Por cobrar (USD)</span>
        <span class="value"><?= Config::dinero($totales['total_usd']) ?></span>
        <span class="delta"><?= $totales['boletas'] ?> boletas abiertas</span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Ya vencido (USD)</span>
        <span class="value"><?= Config::dinero($totales['vencido_usd']) ?></span>
        <span class="delta <?= $totales['vencido_usd'] > 0 ? 'critical' : 'good' ?>"><?= $totales['vencido_usd'] > 0 ? 'Debía estar cobrado' : 'Nada vencido' ?></span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Vence este mes (USD)</span>
        <span class="value"><?= Config::dinero($totales['este_mes_usd']) ?></span>
        <span class="delta">Lo que queda de este mes, hoy incluido</span>
    </div>
</div>

<div class="panel">
    <h2>Por mes de vencimiento</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Vencimiento</th><th class="num">Saldo (USD)</th><th class="num">Boletas</th><th class="num">Clientes</th><th>&nbsp;</th></tr>
            </thead>
            <tbody>
            <?php foreach ($cubetas as $c): ?>
                <tr>
                    <td><?= e($etiqueta($c['clave'])) ?></td>
                    <td class="num"><?= Config::dinero($c['saldo_usd']) ?></td>
                    <td class="num"><?= $c['boletas'] ?></td>
                    <td class="num"><?= $c['clientes'] ?></td>
                    <td class="barra-celda">
                        <span class="hbar-track">
                            <?= barra_svg(
                                'hbar-fill',
                                'width:' . altura_en_pct($c['saldo_usd'], $maximo) . '%',
                                $color($c['clave']),
                                $etiqueta($c['clave']) . ': ' . Config::dinero($c['saldo_usd']),
                                3
                            ) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="subtitulo" style="margin-top:14px;">Los clientes de cada fila son los distintos de esa fila: uno con boletas en dos meses aparece en las dos.</p>
</div>
