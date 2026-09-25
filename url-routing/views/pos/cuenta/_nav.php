<?php /* Expects $activa (0 = inicio, 1-5 = action code). */ ?>
<nav class="acciones" aria-label="Secciones de la cuenta">
<?php foreach ([0 => 'Mi cuenta', 1 => 'Perfil', 2 => 'Órdenes', 3 => 'Deseos', 4 => 'Direcciones', 5 => 'Preferencias'] as $codigo => $nombre): ?>
    <a href="<?= esc($codigo === 0 ? cuenta() : cuenta($codigo)) ?>"<?= $codigo === $activa ? ' aria-current="page"' : '' ?>><?= esc($nombre) ?></a>
<?php endforeach; ?>
</nav>
