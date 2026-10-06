<?php use App\Support\Etiquetas; ob_start(); $activa = 0; include __DIR__ . '/_cabecera.php'; ?>
<h2>Sucesos que documenta</h2>
<?php if ($sucesos === []): ?>
<p class="vacio">No se ha vinculado a ningún suceso.</p>
<?php else: ?>
<ul class="lista">
<?php foreach ($sucesos as $s): ?>
    <li><a href="<?= esc(enlace('suceso', $s['id'])) ?>"><?= esc(Etiquetas::suceso($s['tipo'])) ?></a> <span class="meta"><?= esc(fecha($s['fecha'])) ?></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = $registro['titulo']; include __DIR__ . '/../_layout.php';
