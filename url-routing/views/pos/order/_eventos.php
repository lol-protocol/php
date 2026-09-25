<?php use App\Support\Etiquetas; /* Expects $eventos. */ ?>
<?php if ($eventos === []): ?>
<p class="vacio">Sin movimientos todavía.</p>
<?php else: ?>
<ul class="lista">
<?php foreach ($eventos as $e): ?>
    <li><strong><?= esc(Etiquetas::estadoOrden($e['estado'])) ?></strong> <span class="meta"><?= esc(fecha((string)$e['ocurrido_en'])) ?></span>
<?php if ($e['detalle'] !== null): ?><div><?= esc($e['detalle']) ?></div><?php endif; ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
