<?php use App\Support\Etiquetas; ob_start(); $activa = 4; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($eventos === []): ?>
<p class="vacio">Sin sucesos registrados.</p>
<?php else: ?>
<table>
    <thead><tr><th>Fecha</th><th>Suceso</th><th>Lugar</th><th>Rol</th></tr></thead>
    <tbody>
<?php foreach ($eventos as $e): ?>
        <tr>
            <td><?= esc(fecha($e['fecha'])) ?></td>
            <td><a href="<?= esc(enlace('suceso', $e['id'])) ?>"><?= esc(Etiquetas::suceso($e['tipo'])) ?></a>
<?php if ($e['descripcion'] !== null): ?>
                <div class="meta"><?= esc($e['descripcion']) ?></div>
<?php endif; ?>
            </td>
            <td><?php if ($e['lugar_ruta'] !== null): ?><a href="<?= esc(enlaceLugar(explode('/', $e['lugar_ruta']))) ?>"><?= esc($e['lugar_nombre']) ?></a><?php else: ?>—<?php endif; ?></td>
            <td><?= esc($e['rol']) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Cronología de ' . $persona['nombres'] . ' ' . $persona['apellidos']; include __DIR__ . '/../_layout.php';
