<?php /* Expects $variantes and $producto (for the "agregar al carrito" form). */ ?>
<?php if ($variantes === []): ?>
<p class="vacio">Sin variantes registradas.</p>
<?php else: ?>
<table>
    <thead><tr><th>Variante</th><th>SKU</th><th class="num">Precio</th><th class="num">Disponibles</th><th></th></tr></thead>
    <tbody>
<?php foreach ($variantes as $v): ?>
        <tr>
            <td><?= esc($v['nombre']) ?></td>
            <td class="meta"><?= esc($v['sku']) ?></td>
            <td class="num"><?= esc(dinero($v['precio_centavos'], $v['moneda'])) ?></td>
            <td class="num"><?= (int)$v['stock'] > 0 ? (int)$v['stock'] : '<span class="meta">Agotado</span>' ?></td>
            <td>
<?php if ($producto['activo'] && (int)$v['stock'] > 0): ?>
                <form action="/cart/agregar/" method="post">
                    <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
                    <input type="hidden" name="sku" value="<?= esc($v['sku']) ?>">
                    <input type="number" name="cantidad" value="1" min="1" max="<?= (int)$v['stock'] ?>" style="width: 4em">
                    <button type="submit">Agregar al carrito</button>
                </form>
<?php endif; ?>
            </td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
