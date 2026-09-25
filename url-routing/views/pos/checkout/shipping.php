<?php ob_start(); ?>
<h1>Envío</h1>
<p class="aviso">El proceso de compra todavía no está disponible.</p>
<?php $content = ob_get_clean(); $title = 'Envío'; include __DIR__ . '/../_layout.php';
