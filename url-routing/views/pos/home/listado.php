<?php ob_start(); ?>
<?php
$nombres = ['producto' => 'Productos', 'coleccion' => 'Colecciones', 'etiqueta' => 'Etiquetas',
    'atributo' => 'Atributos', 'grupo' => 'Categorías'];
$pagina_url = static fn(int $p): string => busqueda($digitos, $query, $p);
?>
<?php if (empty($fragmento)): ?>
<h1><?= esc($nombres[$tipo] ?? ucfirst($tipo)) ?></h1>
<p class="sub"><?= $query !== '' ? 'Resultados para «' . esc($query) . '»' : 'Todos' ?><?= $pagina > 1 ? ' · página ' . $pagina : '' ?></p>
<?php endif; ?>
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
<?php include __DIR__ . '/../../_partials/paginacion.php'; ?>
<?php $content = ob_get_clean();
// Only the list and the navigation: what the script at /b.js appends to the page it is on.
if (!empty($fragmento)) {
    echo $content;
    return;
}
$scripts = ['/b.js'];
$title = $nombres[$tipo] ?? ucfirst($tipo); include __DIR__ . '/../_layout.php';
