<?php ob_start(); $activa = 2; include __DIR__ . '/_cabecera.php'; ?>
<?php include __DIR__ . '/_dispersion.php'; ?>
<?php $content = ob_get_clean(); $title = 'Dispersión · ' . $grupo['apellido']; include __DIR__ . '/../_layout.php';
