<?php ob_start(); $activa = 1; include __DIR__ . '/_cabecera.php'; ?>
<?php
$porId = [];
foreach ($personas as $p) {
    $porId[(int)$p['id']] = $p;
}
?>
<?php if ($personas === []): ?>
<p class="vacio">Nadie lleva este apellido todavía.</p>
<?php else: ?>
<table>
    <thead><tr><th>Persona</th><th>Padre</th><th>Madre</th></tr></thead>
    <tbody>
<?php foreach ($personas as $p): ?>
        <tr>
            <td><a href="<?= esc(enlace('persona', $p['id'])) ?>"><?= esc($p['nombres'] . ' ' . $p['apellidos']) ?></a></td>
<?php foreach (['padre_id', 'madre_id'] as $campo): ?>
            <td><?php if ($p[$campo] !== null): ?><a href="<?= esc(enlace('persona', $p[$campo])) ?>"><?= esc(isset($porId[(int)$p[$campo]]) ? $porId[(int)$p[$campo]]['nombres'] : 'otro apellido') ?></a><?php else: ?>—<?php endif; ?></td>
<?php endforeach; ?>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Red · ' . $grupo['apellido']; include __DIR__ . '/../_layout.php';
