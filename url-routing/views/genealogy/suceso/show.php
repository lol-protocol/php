<?php use App\Support\Etiquetas; ob_start(); ?>
<h1><?= esc(Etiquetas::suceso($suceso['tipo'])) ?></h1>
<p class="sub"><?= esc(fecha($suceso['fecha'])) ?>
<?php if ($suceso['lugar_ruta'] !== null): ?>
    · <a href="<?= esc(enlaceLugar(explode('/', $suceso['lugar_ruta']))) ?>"><?= esc($suceso['lugar_nombre']) ?></a>
<?php endif; ?>
</p>
<?php if ($suceso['descripcion'] !== null): ?>
<p><?= esc($suceso['descripcion']) ?></p>
<?php endif; ?>

<h2>Participantes</h2>
<?php if ($participantes === []): ?>
<p class="vacio">Sin participantes registrados.</p>
<?php else: ?>
<table>
<?php foreach ($participantes as $p): ?>
    <tr><th scope="row"><?= esc($p['rol']) ?></th>
        <td><a href="<?= esc(enlace('persona', $p['id'])) ?>"><?= esc($p['nombres'] . ' ' . $p['apellidos']) ?></a></td></tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Documentado en</h2>
<?php if ($registros === []): ?>
<p class="vacio">Ningún registro documenta este suceso todavía.</p>
<?php else: ?>
<ul class="lista">
<?php foreach ($registros as $r): ?>
    <li><a href="<?= esc(enlace('registro', $r['id'])) ?>"><?= esc($r['titulo']) ?></a> <span class="meta"><?= esc(fecha($r['fecha'])) ?></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = Etiquetas::suceso($suceso['tipo']) . ' · ' . fecha($suceso['fecha']); include __DIR__ . '/../_layout.php';
