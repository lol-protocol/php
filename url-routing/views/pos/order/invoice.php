<?php ob_start(); $activa = 1; include __DIR__ . '/_cabecera.php'; ?>
<dl class="ficha">
    <dt>Orden</dt><dd><?= esc($orden['id']) ?></dd>
    <dt>Fecha</dt><dd><?= esc(fecha((string)$orden['creada_en'])) ?></dd>
    <dt>Enviar a</dt><dd><?= esc($orden['direccion_envio'] ?? '—') ?></dd>
</dl>
<h2>Conceptos</h2>
<?php include __DIR__ . '/_items.php'; ?>
<?php $content = ob_get_clean(); $title = 'Factura · Orden ' . $orden['id']; include __DIR__ . '/../_layout.php';
