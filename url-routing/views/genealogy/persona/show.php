<?php use App\Support\Etiquetas; ob_start(); $activa = 0; include __DIR__ . '/_cabecera.php'; ?>
<dl class="ficha">
    <dt>Nacimiento</dt>
    <dd>
        <?= esc(fecha($persona['nacimiento'])) ?>
<?php if ($persona['lugar_nacimiento'] !== null): ?>
        · <a href="<?= esc(enlaceLugar(explode('/', $persona['lugar_nacimiento']))) ?>"><?= esc($persona['lugar_nacimiento']) ?></a>
<?php endif; ?>
    </dd>
<?php if ($persona['defuncion'] !== null): ?>
    <dt>Defunción</dt>
    <dd><?= esc(fecha($persona['defuncion'])) ?></dd>
<?php endif; ?>
    <dt>Sexo</dt>
    <dd><?= esc(['F' => 'Femenino', 'M' => 'Masculino', 'X' => 'No binario'][$persona['sexo']] ?? '—') ?></dd>
</dl>

<h2>Familia</h2>
<?php if ($vinculos === []): ?>
<p class="vacio">Sin familiares registrados.</p>
<?php else: ?>
<table>
<?php foreach ($vinculos as $v): ?>
    <tr>
        <th scope="row"><?= esc(Etiquetas::relacion($v['relacion'])) ?></th>
        <td><a href="<?= esc(enlace('persona', $v['id'])) ?>"><?= esc($v['nombres'] . ' ' . $v['apellidos']) ?></a>
            <span class="meta"><?= esc(vida($v['nacimiento'], $v['defuncion'])) ?></span></td>
    </tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Sucesos</h2>
<?php if ($cronologia === []): ?>
<p class="vacio">Sin sucesos registrados.</p>
<?php else: ?>
<ul class="lista">
<?php foreach ($cronologia as $e): ?>
    <li><a href="<?= esc(enlace('suceso', $e['id'])) ?>"><?= esc(Etiquetas::suceso($e['tipo'])) ?></a>
        <span class="meta"><?= esc(fecha($e['fecha'])) ?><?= $e['lugar_nombre'] !== null ? ' · ' . esc($e['lugar_nombre']) : '' ?></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = $persona['nombres'] . ' ' . $persona['apellidos']; include __DIR__ . '/../_layout.php';
