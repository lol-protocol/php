<?php ob_start(); $activa = 1; include __DIR__ . '/_cabecera.php'; ?>
<dl class="ficha">
    <dt>Custodiado por</dt>
    <dd><?php if ($registro['organizacion_id'] !== null): ?><a href="<?= esc(enlace('organizacion', $registro['organizacion_id'])) ?>"><?= esc($registro['organizacion_nombre']) ?></a><?php else: ?>—<?php endif; ?></dd>
    <dt>Referencia</dt>
    <dd><?= esc($registro['fuente'] ?? '—') ?></dd>
<?php if ($registro['url'] !== null && preg_match('#^https?://#i', $registro['url']) === 1): ?>
    <dt>En línea</dt>
    <dd><a href="<?= esc($registro['url']) ?>" rel="noopener noreferrer"><?= esc($registro['url']) ?></a></dd>
<?php endif; ?>
</dl>
<?php $content = ob_get_clean(); $title = 'Fuente · ' . $registro['titulo']; include __DIR__ . '/../_layout.php';
