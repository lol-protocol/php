<?php /* Expects $lista: rows with id, nombres, apellidos and optionally nacimiento/defuncion. */ ?>
<?php if ($lista === []): ?>
    <p class="vacio">Sin personas registradas.</p>
<?php else: ?>
    <ul class="lista">
<?php foreach ($lista as $p): ?>
        <li>
            <a href="<?= esc(enlace('persona', $p['id'])) ?>"><?= esc($p['nombres'] . ' ' . $p['apellidos']) ?></a>
<?php $v = vida($p['nacimiento'] ?? null, $p['defuncion'] ?? null); if ($v !== ''): ?>
            <span class="meta">(<?= esc($v) ?>)</span>
<?php endif; ?>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
