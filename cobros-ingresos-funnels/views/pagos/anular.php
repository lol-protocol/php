<?php

/** @var array $pago */
$anulado = $pago['anulada'];
$volverHref = '?page=pagos';
$volverTexto = '&larr; Volver a Pagos';
$botonTexto = 'Si, anular este pago';
?>

<h1>Anular pago #<?= (int) $pago['id'] ?></h1>
<p class="subtitulo"><a href="<?= $volverHref ?>"><?= $volverTexto ?></a></p>

<div class="panel">
    <?php if ($anulado): ?>
        <p>Este pago ya está anulado.</p>
    <?php else: ?>
        <p>¿Seguro que querés anular este pago? Deja de contar en los reportes de cobros y, si
           estaba ligado a una boleta, esa boleta vuelve a quedar con el saldo pendiente. Se
           conserva en el historial marcado como anulado.</p>
    <?php endif; ?>
    <table style="margin-bottom:20px;">
        <tr><th>Cliente</th><td><?= htmlspecialchars($pago['cliente']) ?></td></tr>
        <tr><th>Monto</th><td><?= money_moneda((float) $pago['monto'], $pago['moneda_codigo']) ?></td></tr>
        <tr><th>Fecha</th><td><?= htmlspecialchars($pago['fecha_pago']) ?></td></tr>
        <tr><th>Origen</th><td><?= $pago['boleta_id'] ? 'Boleta #' . (int) $pago['boleta_id'] : 'Anticipo' ?></td></tr>
    </table>
    <?php include __DIR__ . '/../_accion_confirmar.php'; ?>
</div>
