<?php ob_start(); ?>
<h1>Tu compra</h1>
<?php $paso = 0; include __DIR__ . '/_pasos.php'; ?>
<?php include __DIR__ . '/_lineas.php'; ?>
<p class="acciones"><a href="/c/">Volver al carrito</a> <a href="/p/1/">Continuar: envío →</a></p>
<?php $content = ob_get_clean(); $title = 'Tu compra'; include __DIR__ . '/../_layout.php';
