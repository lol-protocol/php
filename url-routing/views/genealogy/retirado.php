<?php ob_start(); ?>
<h1>Página retirada</h1>
<p>Esta página se retiró a pedido de una persona, mientras se revisa o de forma definitiva.</p>
<?php $content = ob_get_clean(); $title = 'Página retirada'; $noindex = true; include __DIR__ . '/_layout.php';
