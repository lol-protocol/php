<?php ob_start(); $activa = 1; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($personas === []): ?>
<p class="vacio">No hay nacimientos registrados en este lugar.</p>
<?php else: ?>
<table>
    <thead><tr><th>Persona</th><th>Nacimiento</th><th>Lugar</th></tr></thead>
    <tbody>
<?php foreach ($personas as $p): ?>
        <tr>
            <td><a href="<?= esc(enlace('persona', $p['id'])) ?>"><?= esc($p['nombres'] . ' ' . $p['apellidos']) ?></a></td>
            <td><?= esc(fecha($p['nacimiento'])) ?></td>
            <td><?= esc($p['lugar_nombre']) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Personas · ' . $lugar['nombre']; include __DIR__ . '/../_layout.php';
