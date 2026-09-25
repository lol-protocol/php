<?php use App\Support\Etiquetas; ob_start(); $activa = 2; include __DIR__ . '/_cabecera.php'; ?>
<?php if ($atributos === []): ?>
<p class="vacio">Sin atributos registrados.</p>
<?php else: ?>
<?php $porTipo = []; foreach ($atributos as $a) { $porTipo[$a['tipo']][] = $a; } ?>
<dl class="ficha">
<?php foreach ($porTipo as $tipoAtributo => $lista): ?>
    <dt><?= esc(Etiquetas::atributo($tipoAtributo)) ?></dt>
    <dd><?php foreach ($lista as $i => $a): ?><?= $i > 0 ? ', ' : '' ?><?php if ($tipoAtributo === 'color' && $a['valor'] !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $a['valor']) === 1): ?><span class="muestra" style="background: <?= esc($a['valor']) ?>"></span> <?php endif; ?><a href="<?= esc(enlace('atributo', $a['id'])) ?>"><?= esc($a['nombre']) ?></a><?php endforeach; ?></dd>
<?php endforeach; ?>
</dl>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Atributos · ' . $producto['nombre']; include __DIR__ . '/../_layout.php';
