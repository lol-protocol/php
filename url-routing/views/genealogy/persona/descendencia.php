<?php use App\Support\Etiquetas; ob_start(); $activa = 2; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($descendientes === []): ?>
<p class="vacio">Sin descendientes registrados.</p>
<?php else: ?>
<?php $porGeneracion = []; foreach ($descendientes as $fila) { $porGeneracion[(int)$fila['generacion']][] = $fila; } ?>
<?php foreach ($porGeneracion as $generacion => $lista): ?>
<h2><?= esc(Etiquetas::generacion($generacion, false)) ?></h2>
<?php include __DIR__ . '/../_partials/personas.php'; ?>
<?php endforeach; ?>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Descendencia de ' . $persona['nombres'] . ' ' . $persona['apellidos']; include __DIR__ . '/../_layout.php';
