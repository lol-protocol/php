<?php /* Expects $registro and $activa (0 = ficha, 1 = fuente). */ ?>
<h1><?= esc($registro['titulo']) ?></h1>
<p class="sub"><?= esc(ucfirst($registro['tipo'])) ?> · <?= esc(fecha($registro['fecha'])) ?>
<?php if ($registro['lugar_ruta'] !== null): ?>
    · <a href="<?= esc(enlaceLugar(explode('/', $registro['lugar_ruta']))) ?>"><?= esc($registro['lugar_nombre']) ?></a>
<?php endif; ?>
</p>
<nav class="acciones" aria-label="Secciones del registro">
    <a href="<?= esc(enlace('registro', $registro['id'])) ?>"<?= $activa === 0 ? ' aria-current="page"' : '' ?>>Ficha</a>
    <a href="<?= esc(accion('registro', $registro['id'], 1)) ?>"<?= $activa === 1 ? ' aria-current="page"' : '' ?>>Fuente</a>
</nav>
