<?php

/** @var array $boleta */
$anulado = $boleta['anulada'];
$volverHref = '?page=cobros';
$volverTexto = '&larr; Volver a Cobros e ingresos';
$botonTexto = 'Si, anular esta boleta';
?>

<h1>Anular boleta #<?= (int) $boleta['id'] ?></h1>
<p class="subtitulo"><a href="<?= $volverHref ?>"><?= $volverTexto ?></a></p>

<div class="panel">
    <?php if ($anulado): ?>
        <p>Esta boleta ya está anulada.</p>
    <?php else: ?>
        <p>¿Seguro que querés anular esta boleta? Queda marcada como anulada y deja de contar en los
           reportes de ingresos y cartera, pero se conserva en el historial.</p>
    <?php endif; ?>
    <table style="margin-bottom:20px;">
        <tr><th>Cliente</th><td><?= htmlspecialchars($boleta['cliente']) ?></td></tr>
        <tr><th>Concepto</th><td><?= htmlspecialchars($boleta['concepto']) ?></td></tr>
        <tr><th>Monto</th><td><?= money_moneda((float) $boleta['monto'], $boleta['moneda_codigo']) ?></td></tr>
        <tr><th>Emision</th><td><?= htmlspecialchars($boleta['fecha_emision']) ?></td></tr>
    </table>
    <?php include __DIR__ . '/../_accion_confirmar.php'; ?>
</div>
