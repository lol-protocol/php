<?php ob_start(); ?>
<h1><?= esc($usuario['nombre']) ?></h1>
<p class="sub"><?= esc($usuario['email']) ?> · miembro desde <?= esc(fecha(substr((string)$usuario['creado_en'], 0, 10))) ?></p>
<?php $activa = 0; include __DIR__ . '/_nav.php'; ?>
<?php $content = ob_get_clean(); $title = 'Mi cuenta'; include __DIR__ . '/../_layout.php';
