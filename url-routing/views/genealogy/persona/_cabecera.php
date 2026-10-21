<?php /* Expects $persona and $activa (0 = ficha, 1-5 = action code; 5 is the owner's). */
// A hidden persona's page says nothing but "Persona viva/privada": not worth a search result.
$noindex = $persona['oculta'];
$nombreCompleto = $persona['nombres'] . ' ' . $persona['apellidos'];
$seccionesPersona = [1 => 'Ascendencia', 2 => 'Descendencia', 3 => 'Vínculos', 4 => 'Cronología', 5 => 'Privacidad'];
$migas = [['Personas', busqueda(10)], [$nombreCompleto, $activa === 0 ? null : enlace('persona', $persona['id'])]];
if ($activa !== 0) {
    $migas[] = [$seccionesPersona[$activa], null];
}
// Who she is, for search engines: never for a hidden persona, whose page says nothing.
if ($activa === 0 && !$persona['oculta']) {
    $fecha = static fn(?string $f): ?string => $f !== null && preg_match('/^\d{4}(-\d{2}-\d{2})?$/', $f) === 1 ? $f : null;
    $estructurado = array_filter([
        '@type' => 'Person',
        'name' => $nombreCompleto,
        'url' => \App\Support\HostParser::origen() . enlace('persona', $persona['id']),
        'birthDate' => $fecha($persona['nacimiento']),
        'deathDate' => $fecha($persona['defuncion']),
    ], static fn($v): bool => $v !== null);
} ?>
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
