<?php ob_start(); ?>
<h1>Preferencias</h1>
<?php $activa = 5; include __DIR__ . '/_nav.php'; ?>
<p class="aviso">Las preferencias de cuenta todavía no están disponibles.</p>
<?php $content = ob_get_clean(); $title = 'Preferencias'; include __DIR__ . '/../_layout.php';
