<?php ob_start(); ?>
<?php if ($grupo['padre_id'] !== null): ?>
<nav class="meta" aria-label="Categoría superior"><a href="<?= esc(enlace('grupo', $grupo['padre_id'])) ?>"><?= esc($grupo['padre_nombre']) ?></a></nav>
<?php endif; ?>
<h1><?= esc($grupo['nombre']) ?></h1>
<?php if ($subgrupos !== []): ?>
<nav class="acciones" aria-label="Subcategorías">
<?php foreach ($subgrupos as $s): ?>
    <a href="<?= esc(enlace('grupo', $s['id'])) ?>"><?= esc($s['nombre']) ?></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<?php $lista = $productos; include __DIR__ . '/../_partials/productos.php'; ?>
<?php $content = ob_get_clean(); $title = $grupo['nombre']; include __DIR__ . '/../_layout.php';
