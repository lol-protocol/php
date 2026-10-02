<?php /* Expects $activa (0 = inicio, 1 = colecciones, 2 = aportes). */ ?>
<nav class="acciones" aria-label="Secciones de la cuenta">
    <a href="<?= esc(cuenta()) ?>"<?= $activa === 0 ? ' aria-current="page"' : '' ?>>Mi cuenta</a>
    <a href="<?= esc(cuenta(1)) ?>"<?= $activa === 1 ? ' aria-current="page"' : '' ?>>Mis colecciones</a>
    <a href="<?= esc(cuenta(2)) ?>"<?= $activa === 2 ? ' aria-current="page"' : '' ?>>Mis aportes</a>
</nav>
