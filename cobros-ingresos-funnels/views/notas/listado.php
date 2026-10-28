<?php

use App\Config;
use App\Repositories\NotaCreditoRepository;

/** @var list<array{tipo: string, texto: string}> $avisos */
/** @var string $estado */
/** @var array{cantidad: int, total_usd: float} $resumen */
/** @var array $notas */
/** @var int $totalNotas */
/** @var int $totalPaginas */
/** @var int $pagina */
?>

<h1>Notas de crédito</h1>
<p class="subtitulo">Devoluciones a clientes por boletas anuladas que ya estaban cobradas. Una nota queda pendiente hasta que anotás el día en que se devolvió la plata.</p>

<form class="filtros" method="get">
    <input type="hidden" name="page" value="notas">
    <label for="estado">Mostrar</label>
    <select name="estado" id="estado">
        <?php foreach (NotaCreditoRepository::ESTADOS as $clave => $etiqueta): ?>
            <option value="<?= $clave ?>" <?= $estado === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Aplicar</button>
</form>
<?php include __DIR__ . '/../_avisos.php'; ?>

<div class="grid grid-kpis">
    <div class="panel stat-tile">
        <span class="label">Pendientes de devolver</span>
        <span class="value"><?= $resumen['cantidad'] ?></span>
        <span class="delta <?= $resumen['cantidad'] > 0 ? 'critical' : 'good' ?>"><?= $resumen['cantidad'] > 0 ? 'Plata de clientes que todavía no volvió' : 'No se debe ninguna devolución' ?></span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Total pendiente (USD)</span>
        <span class="value"><?= Config::dinero($resumen['total_usd']) ?></span>
        <span class="delta">A la tasa de hoy, como la cartera pendiente</span>
    </div>
</div>

<div class="panel">
    <h2><?= htmlspecialchars(NotaCreditoRepository::ESTADOS[$estado]) ?> (<?= $totalNotas ?>)</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Emitida</th><th>Cliente</th><th>Boleta</th><th>Motivo</th><th class="num">Monto</th><th>Devolución</th><th>&nbsp;</th></tr>
            </thead>
            <tbody>
            <?php foreach ($notas as $n): ?>
                <tr>
                    <td><?= htmlspecialchars($n['fecha']) ?></td>
                    <td><a href="?page=cliente&id=<?= (int) $n['cliente_id'] ?>"><?= htmlspecialchars($n['cliente']) ?></a></td>
                    <td>Boleta #<?= (int) $n['boleta_id'] ?></td>
                    <td><?= htmlspecialchars($n['motivo']) ?></td>
                    <td class="num"><?= dinero_en_moneda((float) $n['monto'], $n['moneda_codigo']) ?></td>
                    <td>
                        <?php if ($n['fecha_devolucion'] === null): ?>
                            <span class="badge pendiente">Pendiente</span>
                        <?php else: ?>
                            <span class="badge pagada">Devuelta el <?= htmlspecialchars($n['fecha_devolucion']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="acciones">
                        <?php if ($n['fecha_devolucion'] === null): ?>
                            <a href="?page=nota-devolver&id=<?= (int) $n['id'] ?>">Registrar devolución</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$notas): ?>
                <tr><td colspan="7"><?= $estado === 'pendientes' ? 'No hay devoluciones pendientes.' : 'No hay notas para este filtro.' ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../_paginacion.php'; ?>
</div>
