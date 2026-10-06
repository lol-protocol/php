<?php ob_start(); ?>
<h1>Lista de deseos</h1>
<?php $activa = 3; include __DIR__ . '/_nav.php'; ?>
<?php $lista = $deseos; include __DIR__ . '/../_partials/productos.php'; ?>
<?php $content = ob_get_clean(); $title = 'Lista de deseos'; include __DIR__ . '/../_layout.php';
