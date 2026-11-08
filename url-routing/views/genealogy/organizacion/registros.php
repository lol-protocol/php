<?php ob_start(); $activa = 2; include __DIR__ . '/_cabecera.php'; ?>
<?php $lista = $registros; $vacio = 'No custodia registros cargados.'; include __DIR__ . '/../_partials/registros.php'; ?>
<?php $content = ob_get_clean(); $title = 'Registros · ' . $organizacion['nombre']; include __DIR__ . '/../_layout.php';
