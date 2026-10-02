<?php ob_start(); ?>
<h1>Hola, <?= esc($usuario['nombre']) ?></h1>
<?php $activa = 0; include __DIR__ . '/_nav.php'; ?>
<?php $content = ob_get_clean(); $title = 'Mi cuenta'; include __DIR__ . '/../_layout.php';
