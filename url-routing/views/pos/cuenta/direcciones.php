<?php ob_start(); ?>
<h1>Direcciones</h1>
<?php $activa = 4; include __DIR__ . '/_nav.php'; ?>
<?php if ($direcciones === []): ?>
<p class="vacio">No tienes direcciones guardadas.</p>
<?php else: ?>
<ul class="lista">
<?php foreach ($direcciones as $d): ?>
    <li><strong><?= esc($d['alias']) ?></strong><?= $d['principal'] ? ' <span class="meta">(principal)</span>' : '' ?><br>
        <?= esc($d['destinatario']) ?> · <?= esc($d['calle']) ?>, <?= esc($d['ciudad']) ?> <?= esc($d['codigo_postal']) ?>, <?= esc($d['pais']) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Direcciones'; include __DIR__ . '/../_layout.php';
