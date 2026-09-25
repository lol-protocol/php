<?php ob_start(); $activa = 2; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($orden['numero_guia'] !== null): ?>
<p>Número de guía: <strong><?= esc($orden['numero_guia']) ?></strong></p>
<?php endif; ?>
<?php include __DIR__ . '/_eventos.php'; ?>
<?php $content = ob_get_clean(); $title = 'Seguimiento · Orden ' . $orden['id']; include __DIR__ . '/../_layout.php';
