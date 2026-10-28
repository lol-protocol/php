<?php

use App\Config;
use App\Repositories\CobranzaRepository;
use App\Repositories\IngresosYCobrosRepository;

/** @var list<array{tipo: string, texto: string}> $avisos */
/** @var string $alcance */
/** @var string $orden */
/** @var array{clientes: int, saldo_usd: float, vencido_usd: float} $resumen */
/** @var array $clientes */
/** @var int $totalClientes */
/** @var int $totalPaginas */
/** @var int $pagina */
/** @var array{ultima: ?DateTimeImmutable, fuente: ?string, pendientes: list<string>} $tasas */

// El color de cada tramo es el de las insignias de estado: verde al dia, ambar hasta 30
// dias, naranja hasta 60 y rojo mas alla.
$claseDelTramo = ['Al día' => 'pagada', '1-30 días' => 'pendiente', '31-60 días' => 'parcial', '61+ días' => 'vencida'];
?>

<h1>Cobranza</h1>
<p class="subtitulo">Quién debe, cuánto y desde cuándo: la cartera pendiente del Dashboard, cliente por cliente.</p>
<?php include __DIR__ . '/../_tasas_de_cambio.php'; ?>

<form class="filtros" method="get">
    <input type="hidden" name="page" value="cobranza">
    <label for="alcance">Mostrar</label>
    <select name="alcance" id="alcance">
        <?php foreach (CobranzaRepository::ALCANCES as $clave => $etiqueta): ?>
            <option value="<?= $clave ?>" <?= $alcance === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
        <?php endforeach; ?>
    </select>
    <label for="orden">Ordenar por</label>
    <select name="orden" id="orden">
        <?php foreach (CobranzaRepository::ORDENES as $clave => $etiqueta): ?>
            <option value="<?= $clave ?>" <?= $orden === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Aplicar</button>
</form>
<?php include __DIR__ . '/../_avisos.php'; ?>

<div class="grid grid-kpis">
    <div class="panel stat-tile">
        <span class="label">Saldo abierto (USD)</span>
        <span class="value"><?= Config::dinero($resumen['saldo_usd']) ?></span>
        <span class="delta">La cartera pendiente, a la tasa de hoy</span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Ya vencido (USD)</span>
        <span class="value"><?= Config::dinero($resumen['vencido_usd']) ?></span>
        <span class="delta <?= $resumen['vencido_usd'] > 0 ? 'critical' : 'good' ?>"><?= $resumen['vencido_usd'] > 0 ? 'Boletas con el vencimiento ya pasado' : 'Nada vencido' ?></span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Clientes con saldo</span>
        <span class="value"><?= $resumen['clientes'] ?></span>
    </div>
</div>

<div class="panel">
    <h2><?= htmlspecialchars(CobranzaRepository::ALCANCES[$alcance]) ?> (<?= $totalClientes ?>)</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Cliente</th><th>Email</th><th>País</th><th class="num">Boletas</th><th class="num">Saldo</th><th class="num">Vencido</th><th>Más vencida</th><th>&nbsp;</th></tr>
            </thead>
            <tbody>
            <?php foreach ($clientes as $c): ?>
                <?php
                $dias = (int) $c['dias_mayor_vencimiento'];
                $tramo = IngresosYCobrosRepository::tramoDeAntiguedad($dias);
                ?>
                <tr>
                    <td><a href="?page=cliente&id=<?= (int) $c['cliente_id'] ?>"><?= htmlspecialchars($c['nombre']) ?></a></td>
                    <td><a href="mailto:<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></a></td>
                    <td><?= htmlspecialchars($c['pais']) ?> · <?= htmlspecialchars($c['ciudad']) ?></td>
                    <td class="num"><?= (int) $c['boletas'] ?></td>
                    <td class="num"><?= Config::dinero((float) $c['saldo_usd']) ?></td>
                    <td class="num"><?= (float) $c['vencido_usd'] > 0 ? Config::dinero((float) $c['vencido_usd']) : '—' ?></td>
                    <td>
                        <span class="badge <?= $claseDelTramo[$tramo] ?? 'pendiente' ?>"><?= $dias > 0 ? $dias . ' ' . ($dias === 1 ? 'día' : 'días') : 'Al día' ?></span>
                    </td>
                    <td class="acciones">
                        <a href="?page=pago-nuevo&amp;cliente_id=<?= (int) $c['cliente_id'] ?>">+ Registrar pago</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$clientes): ?>
                <tr><td colspan="8"><?= $alcance === 'vencidos' ? 'Ningún cliente tiene saldo vencido.' : 'Ningún cliente tiene saldo abierto.' ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../_paginacion.php'; ?>
</div>
