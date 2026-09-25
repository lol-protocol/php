<?php ob_start(); ?>
<h1><a href="<?= esc(enlace('etiqueta', $etiqueta['id'])) ?>"><?= esc($etiqueta['nombre']) ?></a></h1>
<p class="sub">Productos con esta etiqueta</p>
<?php $lista = $productos; include __DIR__ . '/../_partials/productos.php'; ?>
<?php $content = ob_get_clean(); $title = 'Productos · ' . $etiqueta['nombre']; include __DIR__ . '/../_layout.php';
