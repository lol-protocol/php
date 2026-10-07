<?php ob_start(); ?>
<h1>Carrito</h1>
<?php if ($lineas === []): ?>
<p class="vacio">El carrito está vacío.</p>
<?php else: ?>
<table>
    <thead><tr><th>Producto</th><th class="num">Precio</th><th class="num">Cantidad</th><th class="num">Subtotal</th><th></th></tr></thead>
    <tbody>
<?php foreach ($lineas as $l): ?>
        <tr>
            <td><a href="<?= esc(enlace('producto', $l['producto_id'])) ?>"><?= esc($l['producto_nombre']) ?></a>
                <br><span class="meta"><?= esc($l['variante_nombre']) ?> · <?= esc($l['sku']) ?></span></td>
            <td class="num"><?= esc(dinero($l['precio_centavos'], $l['moneda'])) ?></td>
            <td class="num">
                <form action="/cart/actualizar/" method="post">
                    <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
                    <input type="hidden" name="sku" value="<?= esc($l['sku']) ?>">
                    <input type="number" name="cantidad" value="<?= (int)$l['cantidad'] ?>" min="1" max="<?= (int)$l['stock'] ?>" style="width: 4em">
                    <button type="submit">Actualizar</button>
                </form>
            </td>
            <td class="num"><?= esc(dinero($l['subtotal_centavos'], $l['moneda'])) ?></td>
            <td>
                <form action="/cart/quitar/" method="post">
                    <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
                    <input type="hidden" name="sku" value="<?= esc($l['sku']) ?>">
                    <button type="submit">Quitar</button>
                </form>
            </td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr><td colspan="3"><strong>Total</strong></td><td class="num"><strong><?= esc(dinero($total_centavos, $moneda)) ?></strong></td><td></td></tr>
    </tfoot>
</table>
<p class="acciones">
    <a href="/checkout/">Continuar a pagar</a>
    <form action="/cart/vaciar/" method="post" style="display: inline">
        <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
        <button type="submit">Vaciar carrito</button>
    </form>
</p>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Carrito'; include __DIR__ . '/../_layout.php';
