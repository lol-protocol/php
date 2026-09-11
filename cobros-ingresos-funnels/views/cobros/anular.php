<?php

use App\Csrf;

/** @var array $boleta */
?>

<h1>Anular boleta #<?= (int) $boleta['id'] ?></h1>
<p class="subtitulo"><a href="?page=cobros">&larr; Volver a Cobros e ingresos</a></p>

<div class="panel">
    <?php if ($boleta['anulada']): ?>
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
    <?php if ($boleta['anulada']): ?>
        <a href="?page=cobros">&larr; Volver a Cobros e ingresos</a>
    <?php else: ?>
        <form method="post">
            <?= Csrf::campo() ?>
            <button type="submit" style="background:var(--critical);border-color:var(--critical);color:#fff;padding:9px 16px;border-radius:6px;cursor:pointer;">
                Si, anular esta boleta
            </button>
            <a href="?page=cobros" style="margin-left:12px;">Cancelar</a>
        </form>
    <?php endif; ?>
</div>
