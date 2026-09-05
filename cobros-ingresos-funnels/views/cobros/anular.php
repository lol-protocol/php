<?php

/** @var array $boleta */
?>

<h1>Anular boleta #<?= (int) $boleta['id'] ?></h1>
<p class="subtitulo"><a href="?page=cobros">&larr; Volver a Cobros e ingresos</a></p>

<div class="panel">
    <p>¿Seguro que querés anular esta boleta? Queda marcada como anulada y deja de contar en los
       reportes de ingresos y cartera, pero se conserva en el historial.</p>
    <table style="margin-bottom:20px;">
        <tr><th>Cliente</th><td><?= htmlspecialchars($boleta['cliente']) ?></td></tr>
        <tr><th>Concepto</th><td><?= htmlspecialchars($boleta['concepto']) ?></td></tr>
        <tr><th>Monto</th><td><?= money_moneda((float) $boleta['monto'], $boleta['moneda_codigo']) ?></td></tr>
        <tr><th>Emision</th><td><?= htmlspecialchars($boleta['fecha_emision']) ?></td></tr>
    </table>
    <form method="post">
        <button type="submit" style="background:var(--critical);border-color:var(--critical);color:#fff;padding:9px 16px;border-radius:6px;cursor:pointer;">
            Si, anular esta boleta
        </button>
        <a href="?page=cobros" style="margin-left:12px;">Cancelar</a>
    </form>
</div>
