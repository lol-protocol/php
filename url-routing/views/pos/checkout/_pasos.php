<?php /* Expects $paso: 0 resumen, 1 envío, 2 pago, 3 confirmar. */ ?>
<nav class="acciones" aria-label="Pasos de la compra">
<?php foreach (['/p/' => 'Resumen', '/p/1/' => 'Envío', '/p/2/' => 'Pago', '/p/3/' => 'Confirmar'] as $url => $nombre): ?>
    <a href="<?= esc($url) ?>"<?= array_search($url, ['/p/', '/p/1/', '/p/2/', '/p/3/'], true) === $paso ? ' aria-current="step"' : '' ?>><?= esc($nombre) ?></a>
<?php endforeach; ?>
</nav>
