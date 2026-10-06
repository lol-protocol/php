<?php use App\Support\Etiquetas; /* Expects $orden and $activa (0 = detalle, 1 = factura, 2 = seguimiento, 3 = devolución). */ ?>
<h1>Orden <?= esc($orden['id']) ?></h1>
<p class="sub"><?= esc(Etiquetas::estadoOrden($orden['estado'])) ?> · <?= esc(fecha((string)$orden['creada_en'])) ?> · <?= esc(dinero($orden['total_centavos'], $orden['moneda'])) ?></p>
<nav class="acciones" aria-label="Secciones de la orden">
<?php foreach ([0 => 'Detalle', 1 => 'Factura', 2 => 'Seguimiento', 3 => 'Devolución'] as $codigo => $nombre): ?>
    <a href="<?= esc('/order/' . $orden['id'] . '/' . ($codigo === 0 ? '' : $codigo . '/')) ?>"<?= $codigo === $activa ? ' aria-current="page"' : '' ?>><?= esc($nombre) ?></a>
<?php endforeach; ?>
</nav>
