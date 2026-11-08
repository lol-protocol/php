<?php
/**
 * Previous / next links of a search listing, shared by both sites (the lazy loader in /b.js follows the
 * rel="next" one). Needs $pagina (int), $hayMas (bool) and $pagina_url (callable(int): string).
 *
 * @var int $pagina
 * @var bool $hayMas
 * @var callable(int): string $pagina_url
 */
?>
<nav class="acciones" aria-label="Páginas">
<?php if ($pagina > 1): ?><a href="<?= esc($pagina_url($pagina - 1)) ?>" rel="prev">← Anterior</a><?php endif; ?>
<?php if ($hayMas): ?><a href="<?= esc($pagina_url($pagina + 1)) ?>" rel="next">Siguiente →</a><?php endif; ?>
</nav>
