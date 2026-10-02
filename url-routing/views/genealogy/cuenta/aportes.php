<?php ob_start(); ?>
<h1>Mis aportes</h1>
<?php $activa = 2; include __DIR__ . '/_nav.php'; ?>
<h2>Personas</h2>
<?php $lista = $personas; include __DIR__ . '/../_partials/personas.php'; ?>
<h2>Registros</h2>
<?php if ($registros === []): ?>
<p class="vacio">No has aportado registros.</p>
<?php else: ?>
<ul class="lista">
<?php foreach ($registros as $r): ?>
    <li><a href="<?= esc(enlace('registro', $r['id'])) ?>"><?= esc($r['titulo']) ?></a> <span class="meta"><?= esc(fecha($r['fecha'])) ?></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Mis aportes'; include __DIR__ . '/../_layout.php';
