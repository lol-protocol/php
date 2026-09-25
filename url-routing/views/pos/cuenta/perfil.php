<?php ob_start(); ?>
<h1>Perfil</h1>
<?php $activa = 1; include __DIR__ . '/_nav.php'; ?>
<dl class="ficha">
    <dt>Nombre</dt><dd><?= esc($usuario['nombre']) ?></dd>
    <dt>Correo</dt><dd><?= esc($usuario['email']) ?></dd>
    <dt>Cliente desde</dt><dd><?= esc(fecha(substr((string)$usuario['creado_en'], 0, 10))) ?></dd>
</dl>
<?php $content = ob_get_clean(); $title = 'Perfil'; include __DIR__ . '/../_layout.php';
