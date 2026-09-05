<?php

/** @var array $pago */
?>

<h1>Anular pago #<?= (int) $pago['id'] ?></h1>
<p class="subtitulo"><a href="?page=pagos">&larr; Volver a Pagos</a></p>

<div class="panel">
    <p>¿Seguro que querés anular este pago? Deja de contar en los reportes de cobros y, si
       estaba ligado a una boleta, esa boleta vuelve a quedar con el saldo pendiente. Se
       conserva en el historial marcado como anulado.</p>
    <table style="margin-bottom:20px;">
        <tr><th>Cliente</th><td><?= htmlspecialchars($pago['cliente']) ?></td></tr>
        <tr><th>Monto</th><td><?= money_moneda((float) $pago['monto'], $pago['moneda_codigo']) ?></td></tr>
        <tr><th>Fecha</th><td><?= htmlspecialchars($pago['fecha_pago']) ?></td></tr>
        <tr><th>Origen</th><td><?= $pago['boleta_id'] ? 'Boleta #' . (int) $pago['boleta_id'] : 'Anticipo' ?></td></tr>
    </table>
    <form method="post">
        <button type="submit" style="background:var(--critical);border-color:var(--critical);color:#fff;padding:9px 16px;border-radius:6px;cursor:pointer;">
            Si, anular este pago
        </button>
        <a href="?page=pagos" style="margin-left:12px;">Cancelar</a>
    </form>
</div>
