<?php ob_start(); $activa = 2; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($registros === []): ?>
<p class="vacio">No custodia registros cargados.</p>
<?php else: ?>
<ul class="lista">
<?php foreach ($registros as $r): ?>
    <li><a href="<?= esc(enlace('registro', $r['id'])) ?>"><?= esc($r['titulo']) ?></a> <span class="meta"><?= esc(fecha($r['fecha'])) ?></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Registros · ' . $organizacion['nombre']; include __DIR__ . '/../_layout.php';
