<?php /* Expects $lugar, $jerarquia and $activa (0 = ficha, 1 = personas, 2 = sucesos). */ ?>
<?php $codigos = explode('/', $lugar['ruta']); ?>
<nav class="meta" aria-label="Jerarquía">
<?php foreach ($jerarquia as $i => $nivel): ?>
    <?= $i > 0 ? ' › ' : '' ?><a href="<?= esc(enlaceLugar(explode('/', $nivel['ruta']))) ?>"><?= esc($nivel['nombre']) ?></a>
<?php endforeach; ?>
</nav>
<h1><?= esc($lugar['nombre']) ?></h1>
<p class="sub"><?= esc(['', 'País', 'Región', 'Ciudad'][(int)$lugar['nivel']] ?? '') ?></p>
<nav class="acciones" aria-label="Secciones del lugar">
    <a href="<?= esc(enlaceLugar($codigos)) ?>"<?= $activa === 0 ? ' aria-current="page"' : '' ?>>Ficha</a>
    <a href="<?= esc(accionLugar($codigos, 1)) ?>"<?= $activa === 1 ? ' aria-current="page"' : '' ?>>Personas nacidas aquí</a>
    <a href="<?= esc(accionLugar($codigos, 2)) ?>"<?= $activa === 2 ? ' aria-current="page"' : '' ?>>Sucesos</a>
</nav>
