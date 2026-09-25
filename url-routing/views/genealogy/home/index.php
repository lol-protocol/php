<?php ob_start(); ?>
<h1>Genealogía</h1>
<p class="sub">Busca personas, apellidos, sucesos, registros, colecciones u organizaciones con la barra de arriba.</p>
<p>Cada recurso tiene una dirección corta y estable: una persona es <code>/</code> seguido de su número de 10 dígitos,
un apellido son 6 dígitos, un lugar es su código (<code>/mx/jal/gdl/</code>).</p>
<?php $content = ob_get_clean(); $title = 'Inicio'; include __DIR__ . '/../_layout.php';
