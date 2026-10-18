<?php /* Expects $lineas, $moneda and $total (see CheckoutController::resumen()). */ ?>
<table>
    <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">Subtotal</th></tr></thead>
    <tbody>
<?php foreach ($lineas as $l): ?>
        <tr>
            <td><?= esc($l['producto_nombre']) ?> <span class="meta"><?= esc($l['variante_nombre']) ?></span></td>
            <td class="num"><?= (int)$l['cantidad'] ?></td>
            <td class="num"><?= esc(dinero($l['subtotal_centavos'], $l['moneda'])) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="2"><strong>Total</strong></td><td class="num"><strong><?= esc(dinero($total, $moneda)) ?></strong></td></tr></tfoot>
</table>
