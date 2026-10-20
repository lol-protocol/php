<?php /* Expects $lugar, $jerarquia and $activa (0 = ficha, 1 = personas, 2 = sucesos). */ ?>
<?php $codigos = explode('/', $lugar['ruta']); ?>
<?php
$secciones = [1 => 'Personas nacidas aquí', 2 => 'Sucesos'];
$migas = array_map(static fn(array $n): array => [$n['nombre'], enlaceLugar(explode('/', $n['ruta']))], $jerarquia);
if ($activa === 0) {
    $migas[array_key_last($migas)][1] = null;
} else {
    $migas[] = [$secciones[$activa], null];
}
?>
<h1><?= esc($lugar['nombre']) ?></h1>
<p class="sub"><?= esc(['', 'País', 'Región', 'Ciudad'][(int)$lugar['nivel']] ?? '') ?></p>
<nav class="acciones" aria-label="Secciones del lugar">
    <a href="<?= esc(enlaceLugar($codigos)) ?>"<?= $activa === 0 ? ' aria-current="page"' : '' ?>>Ficha</a>
    <a href="<?= esc(accionLugar($codigos, 1)) ?>"<?= $activa === 1 ? ' aria-current="page"' : '' ?>>Personas nacidas aquí</a>
    <a href="<?= esc(accionLugar($codigos, 2)) ?>"<?= $activa === 2 ? ' aria-current="page"' : '' ?>>Sucesos</a>
</nav>
