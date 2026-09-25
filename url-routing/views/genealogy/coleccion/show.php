<?php ob_start(); $activa = 0; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($coleccion['descripcion'] !== null): ?>
<p><?= esc($coleccion['descripcion']) ?></p>
<?php endif; ?>
<h2>Personas</h2>
<?php $lista = $personas; include __DIR__ . '/../_partials/personas.php'; ?>
<?php $content = ob_get_clean(); $title = $coleccion['nombre']; include __DIR__ . '/../_layout.php';
