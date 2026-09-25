<?php ob_start(); $activa = 0; include __DIR__ . '/_cabecera.php'; ?>
<p>Consulta sus <a href="<?= esc(accion('organizacion', $organizacion['id'], 1)) ?>">miembros</a> y los <a href="<?= esc(accion('organizacion', $organizacion['id'], 2)) ?>">registros que custodia</a>.</p>
<?php $content = ob_get_clean(); $title = $organizacion['nombre']; include __DIR__ . '/../_layout.php';
