<?php ob_start(); $activa = 0; include __DIR__ . '/_cabecera.php'; ?>
<?php include __DIR__ . '/_items.php'; ?>
<h2>Envío</h2>
<p><?= esc($orden['direccion_envio'] ?? '—') ?></p>
<h2>Seguimiento</h2>
<?php include __DIR__ . '/_eventos.php'; ?>
<?php $content = ob_get_clean(); $title = 'Orden ' . $orden['id']; include __DIR__ . '/../_layout.php';
