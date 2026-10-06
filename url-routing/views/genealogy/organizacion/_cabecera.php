<?php /* Expects $organizacion and $activa (0 = ficha, 1 = miembros, 2 = registros). */ ?>
<h1><?= esc($organizacion['nombre']) ?></h1>
<p class="sub"><?= esc(ucfirst($organizacion['tipo'])) ?>
<?php if ($organizacion['lugar_ruta'] !== null): ?>
    · <a href="<?= esc(enlaceLugar(explode('/', $organizacion['lugar_ruta']))) ?>"><?= esc($organizacion['lugar_nombre']) ?></a>
<?php endif; ?>
</p>
<nav class="acciones" aria-label="Secciones de la organización">
    <a href="<?= esc(enlace('organizacion', $organizacion['id'])) ?>"<?= $activa === 0 ? ' aria-current="page"' : '' ?>>Ficha</a>
    <a href="<?= esc(accion('organizacion', $organizacion['id'], 1)) ?>"<?= $activa === 1 ? ' aria-current="page"' : '' ?>>Miembros</a>
    <a href="<?= esc(accion('organizacion', $organizacion['id'], 2)) ?>"<?= $activa === 2 ? ' aria-current="page"' : '' ?>>Registros</a>
</nav>
