<?php ob_start(); ?>
<h1>Mis colecciones</h1>
<?php $activa = 1; include __DIR__ . '/_nav.php'; ?>
<?php if ($colecciones === []): ?>
<p class="vacio">Todavía no tienes colecciones.</p>
<?php else: ?>
<ul class="lista">
<?php foreach ($colecciones as $c): ?>
    <li><a href="<?= esc(enlace('coleccion', $c['id'])) ?>"><?= esc($c['nombre']) ?></a>
        <span class="meta"><?= (int)$c['total_personas'] ?> personas<?= $c['publica'] ? '' : ' · privada' ?></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Mis colecciones'; include __DIR__ . '/../_layout.php';
