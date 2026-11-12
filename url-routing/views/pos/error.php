<?php
/* Expects $estado (the HTTP status), $titulo (plain text) and $detalleHtml (HTML already escaped, or ''). See ErrorPage::page(). */
ob_start(); ?>
<h1><?= esc($estado) ?> - <?= esc($titulo) ?></h1>
<?php if ($detalleHtml !== ''): ?>
<p><?= $detalleHtml ?></p>
<?php endif; ?>
<p><a href="/">Volver al inicio</a> · <a href="/b/8/">Ver el catálogo</a></p>
<?php $content = ob_get_clean(); $title = $titulo; $noindex = true; $sinCanonica = true; include __DIR__ . '/_layout.php';
