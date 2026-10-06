<?php /* Expects $persona and $activa (0 = ficha, 1-4 = action code). */ ?>
<h1><?= esc($persona['nombres'] . ' ' . $persona['apellidos']) ?></h1>
<p class="sub">
<?php $v = vida($persona['nacimiento'], $persona['defuncion']); ?>
    <?= $v !== '' ? esc($v) . ' · ' : '' ?>
<?php if ($persona['grupo_id'] !== null): ?>
    Apellido <a href="<?= esc(enlace('grupo', $persona['grupo_id'])) ?>"><?= esc($persona['grupo_apellido']) ?></a>
<?php endif; ?>
</p>
<nav class="acciones" aria-label="Secciones de la persona">
<?php foreach ([0 => 'Ficha', 1 => 'Ascendencia', 2 => 'Descendencia', 3 => 'Vínculos', 4 => 'Cronología'] as $codigo => $nombre): ?>
    <a href="<?= esc($codigo === 0 ? enlace('persona', $persona['id']) : accion('persona', $persona['id'], $codigo)) ?>"<?= $codigo === $activa ? ' aria-current="page"' : '' ?>><?= esc($nombre) ?></a>
<?php endforeach; ?>
</nav>
