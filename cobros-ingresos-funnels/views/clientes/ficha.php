<?php

/** @var array $cliente */
/** @var array $boletas */
/** @var array $pagos */
/** @var array|null $viajeFunnel */

$estadosLabel = ['pagada' => 'Pagada', 'pendiente' => 'Pendiente', 'parcial' => 'Parcial', 'vencida' => 'Vencida', 'anulada' => 'Anulada'];
$metodoLabel = ['transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'efectivo' => 'Efectivo'];
$canalLabel = ['organico' => 'Organico', 'ads' => 'Ads', 'referido' => 'Referido', 'redes_sociales' => 'Redes sociales', 'email' => 'Email'];

$nacimiento = new DateTimeImmutable($cliente['fecha_nacimiento']);
$edad = $nacimiento->diff(new DateTimeImmutable('today'))->y;

$totalFacturado = array_sum(array_column($boletas, 'monto'));
$totalCobrado = array_sum(array_column($pagos, 'monto'));
?>

<h1><?= htmlspecialchars($cliente['nombre']) ?></h1>
<p class="subtitulo"><a href="?page=clientes">&larr; Volver a Clientes</a></p>

<div class="panel">
    <h2>Perfil</h2>
    <div class="perfil-grid">
        <div class="campo"><span class="label">Email</span><span class="valor"><?= htmlspecialchars($cliente['email']) ?></span></div>
        <div class="campo"><span class="label">Pais</span><span class="valor"><?= htmlspecialchars($cliente['pais_nombre']) ?></span></div>
        <div class="campo"><span class="label">Ciudad</span><span class="valor"><?= htmlspecialchars($cliente['ciudad']) ?></span></div>
        <div class="campo"><span class="label">Idioma</span><span class="valor"><?= htmlspecialchars($cliente['idioma']) ?></span></div>
        <div class="campo"><span class="label">Genero</span><span class="valor"><?= htmlspecialchars($cliente['genero']) ?></span></div>
        <div class="campo"><span class="label">Edad</span><span class="valor"><?= $edad ?> años</span></div>
        <div class="campo"><span class="label">Segmento</span><span class="valor"><?= htmlspecialchars($cliente['segmento']) ?></span></div>
        <div class="campo"><span class="label">Cliente desde</span><span class="valor"><?= htmlspecialchars($cliente['fecha_alta']) ?></span></div>
        <div class="campo"><span class="label">Moneda</span><span class="valor"><?= htmlspecialchars($cliente['moneda_codigo']) ?> (<?= htmlspecialchars($cliente['moneda_simbolo']) ?>)</span></div>
    </div>
</div>

<?php if ($viajeFunnel): ?>
<div class="panel">
    <h2>Recorrido por el funnel</h2>
    <p class="subtitulo">
        Llego por canal <strong><?= $canalLabel[$viajeFunnel['canal']] ?? htmlspecialchars($viajeFunnel['canal']) ?></strong>.
    </p>
    <div class="perfil-grid">
        <div class="campo"><span class="label">Visita</span><span class="valor"><?= htmlspecialchars($viajeFunnel['fecha_visita']) ?></span></div>
        <div class="campo"><span class="label">Registro</span><span class="valor"><?= htmlspecialchars($viajeFunnel['fecha_registro'] ?? '—') ?></span></div>
        <div class="campo"><span class="label">Lead</span><span class="valor"><?= htmlspecialchars($viajeFunnel['fecha_lead'] ?? '—') ?></span></div>
        <div class="campo"><span class="label">Conversion</span><span class="valor"><?= htmlspecialchars($viajeFunnel['fecha_conversion'] ?? '—') ?></span></div>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-kpis">
    <div class="panel stat-tile">
        <span class="label">Boletas emitidas</span>
        <span class="value"><?= count($boletas) ?></span>
        <span class="delta"><?= money_moneda($totalFacturado, $cliente['moneda_codigo']) ?> en total</span>
    </div>
    <div class="panel stat-tile">
        <span class="label">Pagos recibidos</span>
        <span class="value"><?= count($pagos) ?></span>
        <span class="delta"><?= money_moneda($totalCobrado, $cliente['moneda_codigo']) ?> en total</span>
    </div>
</div>

<div class="panel">
    <h2>Boletas</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Concepto</th><th>Emision</th><th>Vencimiento</th><th class="num">Monto</th><th class="num">Saldo</th><th>Estado</th></tr>
            </thead>
            <tbody>
            <?php foreach ($boletas as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['concepto']) ?></td>
                    <td><?= htmlspecialchars($b['fecha_emision']) ?></td>
                    <td><?= htmlspecialchars($b['fecha_vencimiento']) ?></td>
                    <td class="num"><?= money_moneda((float) $b['monto'], $b['moneda_codigo']) ?></td>
                    <td class="num"><?= money_moneda((float) $b['saldo'], $b['moneda_codigo']) ?></td>
                    <td><span class="badge <?= $b['estado'] ?>"><?= $estadosLabel[$b['estado']] ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$boletas): ?>
                <tr><td colspan="6">Todavia no tiene boletas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <h2>Pagos</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Fecha</th><th>Metodo</th><th>Origen</th><th class="num">Monto</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pagos as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['fecha_pago']) ?></td>
                    <td><?= $metodoLabel[$p['metodo']] ?? htmlspecialchars($p['metodo']) ?></td>
                    <td><?= $p['boleta_id'] ? 'Boleta #' . (int) $p['boleta_id'] : 'Anticipo' ?></td>
                    <td class="num"><?= money_moneda((float) $p['monto'], $p['moneda_codigo']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pagos): ?>
                <tr><td colspan="4">Todavia no tiene pagos.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
