<?php ob_start(); $activa = 1; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($miembros === []): ?>
<p class="vacio">Sin miembros registrados.</p>
<?php else: ?>
<table>
    <thead><tr><th>Persona</th><th>Rol</th><th>Desde</th><th>Hasta</th></tr></thead>
    <tbody>
<?php foreach ($miembros as $m): ?>
        <tr>
            <td><a href="<?= esc(enlace('persona', $m['id'])) ?>"><?= esc($m['nombres'] . ' ' . $m['apellidos']) ?></a></td>
            <td><?= esc($m['rol']) ?></td>
            <td><?= esc(fecha($m['desde'])) ?></td>
            <td><?= esc(fecha($m['hasta'])) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Miembros · ' . $organizacion['nombre']; include __DIR__ . '/../_layout.php';
