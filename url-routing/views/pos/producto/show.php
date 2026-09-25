<?php ob_start(); $activa = 0; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($producto['descripcion'] !== null): ?>
<p><?= esc($producto['descripcion']) ?></p>
<?php endif; ?>
<?php if ($etiquetas !== []): ?>
<p><?php foreach ($etiquetas as $i => $e): ?><?= $i > 0 ? ' · ' : '' ?><a href="<?= esc(enlace('etiqueta', $e['id'])) ?>"><?= esc($e['nombre']) ?></a><?php endforeach; ?></p>
<?php endif; ?>
<h2>Variantes</h2>
<?php include __DIR__ . '/_variantes.php'; ?>
<?php $content = ob_get_clean(); $title = $producto['nombre']; include __DIR__ . '/../_layout.php';
