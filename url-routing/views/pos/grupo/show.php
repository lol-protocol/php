<?php ob_start(); ?>
<?php if ($grupo['padre_id'] !== null): ?>
<nav class="meta" aria-label="Categoría superior"><a href="<?= esc(enlace('grupo', $grupo['padre_id'])) ?>"><?= esc($grupo['padre_nombre']) ?></a></nav>
<?php endif; ?>
<h1><?= esc($grupo['nombre']) ?></h1>
<?php if ($subgrupos !== []): ?>
<nav class="acciones" aria-label="Subcategorías">
<?php foreach ($subgrupos as $s): ?>
    <a href="<?= esc(enlace('grupo', $s['id'])) ?>"><?= esc($s['nombre']) ?></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<?php
// The filters on this page, each with the page that drops it, and the ones still on offer, each with the page that adds it.
$etiquetaDe = static fn(string $tipo, array $fila): string => $tipo === 'atributo' ? $fila['tipo'] . ': ' . $fila['nombre'] : 'etiqueta: ' . $fila['nombre'];
$sinFiltro = static fn(string $quitar): string => filtrado('grupo', $grupo['id'], array_diff_key(array_map(static fn(array $f): int => (int)$f['id'], $filtros), [$quitar => true]));
$conFiltro = static fn(string $tipo, array $fila): string => filtrado('grupo', $grupo['id'], array_map(static fn(array $f): int => (int)$f['id'], $filtros) + [$tipo => (int)$fila['id']]);
$porOfrecer = [];
foreach ($ofrecidos as $tipo => $filas) {
    if (!isset($filtros[$tipo])) {
        foreach ($filas as $fila) {
            $porOfrecer[] = [$tipo, $fila];
        }
    }
}
?>
<?php if ($filtros !== []): ?>
<nav class="acciones" aria-label="Filtros aplicados">
<?php foreach ($filtros as $tipo => $fila): ?>
    <a href="<?= esc($sinFiltro($tipo)) ?>" aria-current="true" title="Quitar este filtro"><?= esc($etiquetaDe($tipo, $fila)) ?> ✕</a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if ($porOfrecer !== []): ?>
<nav class="acciones" aria-label="Filtrar por">
<?php foreach ($porOfrecer as [$tipo, $fila]): ?>
    <a href="<?= esc($conFiltro($tipo, $fila)) ?>" rel="nofollow"><?= esc($etiquetaDe($tipo, $fila)) ?></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<?php $lista = $productos; include __DIR__ . '/../_partials/productos.php'; ?>
<?php
// A filtered page is one of many combinations of the same products: out of the search results.
$noindex = $filtros !== [];
$content = ob_get_clean(); $title = $grupo['nombre']; include __DIR__ . '/../_layout.php';
