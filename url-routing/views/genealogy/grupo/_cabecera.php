<?php /* Expects $grupo and $activa (0 = ficha, 1 = red, 2 = dispersión). */ ?>
<h1><?= esc($grupo['apellido']) ?></h1>
<p class="sub"><?= (int)$grupo['total_personas'] ?> personas<?= $grupo['origen'] !== null ? ' · origen: ' . esc($grupo['origen']) : '' ?></p>
<nav class="acciones" aria-label="Secciones del apellido">
    <a href="<?= esc(enlace('grupo', $grupo['id'])) ?>"<?= $activa === 0 ? ' aria-current="page"' : '' ?>>Ficha</a>
    <a href="<?= esc(accion('grupo', $grupo['id'], 1)) ?>"<?= $activa === 1 ? ' aria-current="page"' : '' ?>>Red familiar</a>
    <a href="<?= esc(accion('grupo', $grupo['id'], 2)) ?>"<?= $activa === 2 ? ' aria-current="page"' : '' ?>>Dispersión</a>
</nav>
