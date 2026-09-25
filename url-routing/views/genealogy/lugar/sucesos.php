<?php use App\Support\Etiquetas; ob_start(); $activa = 2; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($sucesos === []): ?>
<p class="vacio">No hay sucesos registrados en este lugar.</p>
<?php else: ?>
<table>
    <thead><tr><th>Fecha</th><th>Suceso</th><th>Lugar</th></tr></thead>
    <tbody>
<?php foreach ($sucesos as $s): ?>
        <tr>
            <td><?= esc(fecha($s['fecha'])) ?></td>
            <td><a href="<?= esc(enlace('suceso', $s['id'])) ?>"><?= esc(Etiquetas::suceso($s['tipo'])) ?></a></td>
            <td><?= esc($s['lugar_nombre']) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Sucesos · ' . $lugar['nombre']; include __DIR__ . '/../_layout.php';
