<?php ob_start(); $activa = 1; include __DIR__ . '/_cabecera.php'; ?>
<?php include __DIR__ . '/_variantes.php'; ?>
<?php $content = ob_get_clean(); $title = 'Variantes · ' . $producto['nombre']; include __DIR__ . '/../_layout.php';
