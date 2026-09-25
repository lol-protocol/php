<?php
/** @var int $pagina */
/** @var int $totalPaginas */
?>
<?php if ($totalPaginas > 1): ?>
    <div class="paginacion">
        <?php if ($pagina > 1): ?>
            <a href="<?= htmlspecialchars(url_con_parametro('pagina', $pagina - 1)) ?>">&larr; Anterior</a>
        <?php else: ?>
            <span class="inactivo">&larr; Anterior</span>
        <?php endif; ?>
        <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
        <?php if ($pagina < $totalPaginas): ?>
            <a href="<?= htmlspecialchars(url_con_parametro('pagina', $pagina + 1)) ?>">Siguiente &rarr;</a>
        <?php else: ?>
            <span class="inactivo">Siguiente &rarr;</span>
        <?php endif; ?>
    </div>
<?php endif; ?>
