<?php ob_start(); $activa = 0; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($hijos !== []): ?>
<h2><?= (int)$lugar['nivel'] === 1 ? 'Regiones' : 'Ciudades' ?></h2>
<ul class="lista">
<?php foreach ($hijos as $h): ?>
    <li><a href="<?= esc(enlaceLugar(explode('/', $h['ruta']))) ?>"><?= esc($h['nombre']) ?></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = $lugar['nombre']; include __DIR__ . '/../_layout.php';
