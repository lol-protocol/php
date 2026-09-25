<?php ob_start(); ?>
<h1><?= esc($coleccion['nombre']) ?></h1>
<p class="sub">
<?php if ($coleccion['inicio'] !== null || $coleccion['fin'] !== null): ?>
    Del <?= esc(fecha($coleccion['inicio'])) ?> al <?= esc(fecha($coleccion['fin'])) ?>
<?php endif; ?>
</p>
<?php if ($coleccion['descripcion'] !== null): ?>
<p><?= esc($coleccion['descripcion']) ?></p>
<?php endif; ?>
<?php $lista = $productos; include __DIR__ . '/../_partials/productos.php'; ?>
<?php $content = ob_get_clean(); $title = $coleccion['nombre']; include __DIR__ . '/../_layout.php';
