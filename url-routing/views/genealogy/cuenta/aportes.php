<?php ob_start(); ?>
<h1>Mis aportes</h1>
<?php $activa = 2; include __DIR__ . '/_nav.php'; ?>
<h2>Personas</h2>
<?php $lista = $personas; include __DIR__ . '/../_partials/personas.php'; ?>
<h2>Registros</h2>
<?php $lista = $registros; $vacio = 'No has aportado registros.'; include __DIR__ . '/../_partials/registros.php'; ?>
<?php $content = ob_get_clean(); $title = 'Mis aportes'; include __DIR__ . '/../_layout.php';
