<?php /* Expects $items and $orden. */ ?>
<table>
    <thead><tr><th>Artículo</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">Subtotal</th></tr></thead>
    <tbody>
<?php foreach ($items as $i): ?>
        <tr>
            <td><a href="<?= esc(enlace('producto', $i['producto_id'])) ?>"><?= esc($i['nombre']) ?></a> <span class="meta"><?= esc($i['sku']) ?></span></td>
            <td class="num"><?= (int)$i['cantidad'] ?></td>
            <td class="num"><?= esc(dinero($i['precio_unitario_centavos'], $orden['moneda'])) ?></td>
            <td class="num"><?= esc(dinero($i['subtotal_centavos'], $orden['moneda'])) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot><tr><th colspan="3">Total</th><th class="num"><?= esc(dinero($orden['total_centavos'], $orden['moneda'])) ?></th></tr></tfoot>
</table>
