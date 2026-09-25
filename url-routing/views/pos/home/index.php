<?php ob_start(); ?>
<h1>Contrastocolor</h1>
<p class="sub">Busca productos, categorías, colecciones, etiquetas o colores con la barra de arriba.</p>
<p><a href="/?t=8">Ver todo el catálogo →</a></p>
<?php $content = ob_get_clean(); $title = 'Inicio'; include __DIR__ . '/../_layout.php';
