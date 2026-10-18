<?php ob_start(); ?>
<h1>Pago</h1>
<?php $paso = 2; include __DIR__ . '/_pasos.php'; ?>
<p class="aviso">Por ahora no cobramos en línea. Al confirmar, tu orden queda <strong>pendiente</strong>
y te contactamos para coordinar el pago; la enviamos cuando lo recibimos.</p>
<p class="acciones"><a href="/p/1/">← Envío</a> <a href="/p/3/">Continuar: confirmar →</a></p>
<?php $content = ob_get_clean(); $title = 'Pago'; include __DIR__ . '/../_layout.php';
