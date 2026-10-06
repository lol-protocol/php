<?php ob_start(); $activa = 0; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($grupo['descripcion'] !== null): ?>
<p><?= esc($grupo['descripcion']) ?></p>
<?php endif; ?>
<h2>Dónde nacieron</h2>
<?php include __DIR__ . '/_dispersion.php'; ?>
<?php $content = ob_get_clean(); $title = $grupo['apellido']; include __DIR__ . '/../_layout.php';
