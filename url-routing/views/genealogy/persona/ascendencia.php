<?php use App\Support\Etiquetas; ob_start(); $activa = 1; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($ancestros === []): ?>
<p class="vacio">Sin antepasados registrados.</p>
<?php else: ?>
<?php $porGeneracion = []; foreach ($ancestros as $fila) { $porGeneracion[(int)$fila['generacion']][] = $fila; } ?>
<?php foreach ($porGeneracion as $generacion => $lista): ?>
<h2><?= esc(Etiquetas::generacion($generacion, true)) ?></h2>
<?php include __DIR__ . '/../_partials/personas.php'; ?>
<?php endforeach; ?>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Ascendencia de ' . $persona['nombres'] . ' ' . $persona['apellidos']; include __DIR__ . '/../_layout.php';
