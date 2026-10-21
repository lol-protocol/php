<?php use App\Support\Etiquetas; ob_start(); ?>
<h1>Mis órdenes</h1>
<?php $activa = 2; include __DIR__ . '/_nav.php'; ?>
<?php if ($ordenes === []): ?>
<p class="vacio">Todavía no has hecho ninguna compra.</p>
<?php else: ?>
<table>
    <thead><tr><th>Orden</th><th>Fecha</th><th>Estado</th><th class="num">Total</th></tr></thead>
    <tbody>
<?php foreach ($ordenes as $o): ?>
        <tr>
            <td><?php if ($o['codigo'] !== null): ?><a href="<?= esc(enlace('orden', $o['codigo'])) ?>"><?= esc($o['codigo']) ?></a><?php else: ?><?= esc($o['id']) ?><?php endif; ?></td>
            <td><?= esc(fecha((string)$o['creada_en'])) ?></td>
            <td><?= esc(Etiquetas::estadoOrden($o['estado'])) ?></td>
            <td class="num"><?= esc(dinero($o['total_centavos'], $o['moneda'])) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Mis órdenes'; include __DIR__ . '/../_layout.php';
