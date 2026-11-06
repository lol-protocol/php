<?php
/** @var list<array{tipo: string, texto: string}>|null $avisos */
foreach ($avisos ?? [] as $aviso): ?>
    <p class="aviso aviso-<?= e($aviso['tipo']) ?>" role="status"><?= e($aviso['texto']) ?></p>
<?php endforeach; ?>
