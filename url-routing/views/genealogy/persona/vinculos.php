<?php use App\Support\Etiquetas; ob_start(); $activa = 3; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($vinculos === []): ?>
<p class="vacio">Sin vínculos registrados.</p>
<?php else: ?>
<?php $porRelacion = []; foreach ($vinculos as $fila) { $porRelacion[$fila['relacion']][] = $fila; } ?>
<?php foreach ($porRelacion as $relacion => $lista): ?>
<h2><?= esc(Etiquetas::relacion($relacion)) ?></h2>
<?php include __DIR__ . '/../_partials/personas.php'; ?>
<?php endforeach; ?>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Vínculos de ' . $persona['nombres'] . ' ' . $persona['apellidos']; include __DIR__ . '/../_layout.php';
