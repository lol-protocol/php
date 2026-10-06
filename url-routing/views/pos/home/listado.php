<?php ob_start(); ?>
<?php
$nombres = ['producto' => 'Productos', 'coleccion' => 'Colecciones', 'etiqueta' => 'Etiquetas',
    'atributo' => 'Atributos', 'grupo' => 'Categorías'];
$pagina_url = static fn(int $p): string => '/?' . http_build_query(['t' => $digitos, 'q' => $query, 'p' => $p]);
?>
<h1><?= esc($nombres[$tipo] ?? ucfirst($tipo)) ?></h1>
<p class="sub"><?= $query !== '' ? 'Resultados para «' . esc($query) . '»' : 'Todos' ?><?= $pagina > 1 ? ' · página ' . $pagina : '' ?></p>
<?php if ($tipo === 'producto'): ?>
<?php $lista = $resultados; include __DIR__ . '/../_partials/productos.php'; ?>
<?php elseif ($resultados === []): ?>
<p class="vacio">Sin resultados.</p>
<?php else: ?>
<ul class="lista">
<?php foreach ($resultados as $r): ?>
    <li><a href="<?= esc(enlace($tipo, $r['id'])) ?>"><?= esc($r['nombre']) ?></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<nav class="acciones" aria-label="Páginas">
<?php if ($pagina > 1): ?><a href="<?= esc($pagina_url($pagina - 1)) ?>">← Anterior</a><?php endif; ?>
<?php if ($hayMas): ?><a href="<?= esc($pagina_url($pagina + 1)) ?>">Siguiente →</a><?php endif; ?>
</nav>
<?php $content = ob_get_clean(); $title = $nombres[$tipo] ?? ucfirst($tipo); include __DIR__ . '/../_layout.php';
