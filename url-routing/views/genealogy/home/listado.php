<?php use App\Support\Etiquetas; ob_start(); ?>
<?php
$nombres = ['persona' => 'Personas', 'suceso' => 'Sucesos', 'registro' => 'Registros', 'coleccion' => 'Colecciones',
    'grupo' => 'Apellidos', 'organizacion' => 'Organizaciones'];
$etiqueta = static fn(array $r): string => match ($tipo) {
    'persona' => $r['nombres'] . ' ' . $r['apellidos'],
    'suceso' => Etiquetas::suceso($r['tipo']) . ($r['lugar_nombre'] !== null ? ' en ' . $r['lugar_nombre'] : ''),
    'registro' => $r['titulo'],
    'grupo' => $r['apellido'],
    default => $r['nombre'],
};
$detalle = static fn(array $r): string => match ($tipo) {
    'persona' => vida($r['nacimiento'], $r['defuncion']),
    'suceso', 'registro' => fecha($r['fecha']),
    'organizacion' => ucfirst($r['tipo']),
    default => '',
};
$pagina_url = static fn(int $p): string => '/?' . http_build_query(['t' => $digitos, 'q' => $query, 'p' => $p]);
?>
<h1><?= esc($nombres[$tipo] ?? ucfirst($tipo)) ?></h1>
<p class="sub"><?= $query !== '' ? 'Resultados para «' . esc($query) . '»' : 'Todos' ?><?= $pagina > 1 ? ' · página ' . $pagina : '' ?></p>
<?php if ($resultados === []): ?>
<p class="vacio">Sin resultados.</p>
<?php else: ?>
<ul class="lista">
<?php foreach ($resultados as $r): ?>
    <li><a href="<?= esc(enlace($tipo, $r['id'])) ?>"><?= esc($etiqueta($r)) ?></a>
<?php $d = $detalle($r); if ($d !== ''): ?> <span class="meta"><?= esc($d) ?></span><?php endif; ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<nav class="acciones" aria-label="Páginas">
<?php if ($pagina > 1): ?><a href="<?= esc($pagina_url($pagina - 1)) ?>">← Anterior</a><?php endif; ?>
<?php if ($hayMas): ?><a href="<?= esc($pagina_url($pagina + 1)) ?>">Siguiente →</a><?php endif; ?>
</nav>
<?php $content = ob_get_clean(); $title = $nombres[$tipo] ?? ucfirst($tipo); include __DIR__ . '/../_layout.php';
