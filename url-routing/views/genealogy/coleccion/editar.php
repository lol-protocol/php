<?php ob_start(); $activa = 2; include __DIR__ . '/_cabecera.php'; ?>
<p class="aviso">La edición todavía no está disponible: esta página muestra el contenido actual de la colección.</p>
<dl class="ficha">
    <dt>Nombre</dt><dd><?= esc($coleccion['nombre']) ?></dd>
    <dt>Descripción</dt><dd><?= esc($coleccion['descripcion'] ?? '—') ?></dd>
    <dt>Visibilidad</dt><dd><?= $coleccion['publica'] ? 'Pública' : 'Privada' ?></dd>
</dl>
<h2>Personas</h2>
<?php $lista = $personas; include __DIR__ . '/../_partials/personas.php'; ?>
<?php $content = ob_get_clean(); $title = 'Editar · ' . $coleccion['nombre']; include __DIR__ . '/../_layout.php';
