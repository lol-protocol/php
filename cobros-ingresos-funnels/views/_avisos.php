<?php
/** @var list<array{tipo: string, texto: string}>|null $avisos */
foreach ($avisos ?? [] as $aviso): ?>
    <p class="aviso aviso-<?= htmlspecialchars($aviso['tipo']) ?>" role="status"><?= htmlspecialchars($aviso['texto']) ?></p>
<?php endforeach; ?>
