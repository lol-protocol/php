<?php /* Expects $coleccion and $activa (0 = ficha, 1 = vista, 2 = editar). */ ?>
<h1><?= esc($coleccion['nombre']) ?></h1>
<p class="sub">
    Por <?= esc($coleccion['autor']) ?> · <?= (int)$coleccion['total_personas'] ?> personas
    <?= $coleccion['publica'] ? '' : ' · <strong>privada</strong>' ?>
</p>
<nav class="acciones" aria-label="Secciones de la colección">
    <a href="<?= esc(enlace('coleccion', $coleccion['id'])) ?>"<?= $activa === 0 ? ' aria-current="page"' : '' ?>>Ficha</a>
    <a href="<?= esc(accion('coleccion', $coleccion['id'], 1)) ?>"<?= $activa === 1 ? ' aria-current="page"' : '' ?>>Árbol</a>
    <a href="<?= esc(accion('coleccion', $coleccion['id'], 2)) ?>"<?= $activa === 2 ? ' aria-current="page"' : '' ?>>Editar</a>
    <a href="<?= esc(accion('coleccion', $coleccion['id'], 3)) ?>">Exportar GEDCOM</a>
</nav>
