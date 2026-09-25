<?php /* Expects $lista: rows with id, nombre, precio_centavos, moneda. */ ?>
<?php if ($lista === []): ?>
    <p class="vacio">Sin productos disponibles.</p>
<?php else: ?>
    <ul class="lista">
<?php foreach ($lista as $p): ?>
        <li><a href="<?= esc(enlace('producto', $p['id'])) ?>"><?= esc($p['nombre']) ?></a>
            <span class="meta"><?= esc(dinero($p['precio_centavos'], $p['moneda'])) ?></span></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
