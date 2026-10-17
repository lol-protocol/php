<?php /* Expects $persona and $activa (0 = ficha, 1-5 = action code; 5 is the owner's). */ ?>
<h1><?= esc($persona['nombres'] . ' ' . $persona['apellidos']) ?></h1>
<p class="sub">
<?php $v = vida($persona['nacimiento'], $persona['defuncion']); ?>
    <?= $v !== '' ? esc($v) . ' · ' : '' ?>
<?php if ($persona['grupo_id'] !== null): ?>
    Apellido <a href="<?= esc(enlace('grupo', $persona['grupo_id'])) ?>"><?= esc($persona['grupo_apellido']) ?></a>
<?php endif; ?>
</p>
<?php if ($persona['oculta']): ?>
<p class="aviso">La información de las personas que podrían estar vivas, o que solo figuran en colecciones privadas, no se muestra.</p>
<?php endif; ?>
<nav class="acciones" aria-label="Secciones de la persona">
<?php $secciones = [0 => 'Ficha', 1 => 'Ascendencia', 2 => 'Descendencia', 3 => 'Vínculos', 4 => 'Cronología']; ?>
<?php if (es_propietario()) { $secciones[5] = 'Privacidad'; } ?>
<?php foreach ($secciones as $codigo => $nombre): ?>
    <a href="<?= esc($codigo === 0 ? enlace('persona', $persona['id']) : accion('persona', $persona['id'], $codigo)) ?>"<?= $codigo === $activa ? ' aria-current="page"' : '' ?>><?= esc($nombre) ?></a>
<?php endforeach; ?>
</nav>
