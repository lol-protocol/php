<?php use App\Support\Etiquetas; ob_start(); ?>
<h1><a href="<?= esc(enlace('atributo', $atributo['id'])) ?>"><?= esc($atributo['nombre']) ?></a></h1>
<p class="sub">Productos · <?= esc(Etiquetas::atributo($atributo['tipo'])) ?></p>
<?php $lista = $productos; include __DIR__ . '/../_partials/productos.php'; ?>
<?php $content = ob_get_clean(); $title = 'Productos · ' . $atributo['nombre']; include __DIR__ . '/../_layout.php';
