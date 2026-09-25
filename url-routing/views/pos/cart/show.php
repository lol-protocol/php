<?php ob_start(); ?>
<h1>Carrito</h1>
<p class="aviso">El carrito todavía no está disponible.</p>
<?php $content = ob_get_clean(); $title = 'Carrito'; include __DIR__ . '/../_layout.php';
