<?php /* Expects $lista: rows with id, titulo and fecha; $vacio: what to say when there are none. */ ?>
<?php if ($lista === []): ?>
<p class="vacio"><?= esc($vacio) ?></p>
<?php else: ?>
<ul class="lista">
<?php foreach ($lista as $r): ?>
    <li><a href="<?= esc(enlace('registro', $r['id'])) ?>"><?= esc($r['titulo']) ?></a> <span class="meta"><?= esc(fecha($r['fecha'])) ?></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
