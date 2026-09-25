<?php ob_start(); $activa = 3; include __DIR__ . '/_cabecera.php'; ?>
<?php if (in_array($orden['estado'], ['enviada', 'entregada'], true)): ?>
<p class="aviso">Las solicitudes de devolución en línea todavía no están disponibles. Estos son los artículos de la orden:</p>
<?php include __DIR__ . '/_items.php'; ?>
<?php else: ?>
<p class="aviso">Solo se pueden devolver órdenes enviadas o entregadas.</p>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Devolución · Orden ' . $orden['id']; include __DIR__ . '/../_layout.php';
