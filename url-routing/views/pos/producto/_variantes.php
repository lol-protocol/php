<?php /* Expects $variantes. */ ?>
<?php if ($variantes === []): ?>
<p class="vacio">Sin variantes registradas.</p>
<?php else: ?>
<table>
    <thead><tr><th>Variante</th><th>SKU</th><th class="num">Precio</th><th class="num">Disponibles</th></tr></thead>
    <tbody>
<?php foreach ($variantes as $v): ?>
        <tr>
            <td><?= esc($v['nombre']) ?></td>
            <td class="meta"><?= esc($v['sku']) ?></td>
            <td class="num"><?= esc(dinero($v['precio_centavos'], $v['moneda'])) ?></td>
            <td class="num"><?= (int)$v['stock'] > 0 ? (int)$v['stock'] : '<span class="meta">Agotado</span>' ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
