<?php ob_start(); ?>
<h1><?= esc(t('retirado.titulo')) ?></h1>
<p><?= esc(t('retirado.texto')) ?></p>
<?php $content = ob_get_clean(); $title = t('retirado.titulo'); $noindex = true; include __DIR__ . '/_layout.php';
