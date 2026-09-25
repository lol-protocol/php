<?php ob_start(); ?>
<h1>Método de pago</h1>
<p class="aviso">El proceso de compra todavía no está disponible.</p>
<?php $content = ob_get_clean(); $title = 'Método de pago'; include __DIR__ . '/../_layout.php';
