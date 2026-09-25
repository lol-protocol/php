<?php ob_start(); ?>
<h1><?= esc($etiqueta['nombre']) ?></h1>
<p class="sub">Etiqueta</p>
<p><a href="<?= esc(accion('etiqueta', $etiqueta['id'], 1)) ?>">Ver productos con esta etiqueta →</a></p>
<?php $content = ob_get_clean(); $title = $etiqueta['nombre']; include __DIR__ . '/../_layout.php';
