<?php
/** @var string|null $masRecientes cursor para pedir las filas mas recientes que las que se ven, o null si no hay */
/** @var string|null $masAntiguas cursor para pedir las filas mas antiguas que las que se ven, o null si no hay */
?>
<?php if ($masRecientes !== null || $masAntiguas !== null): ?>
    <div class="paginacion">
        <?php if ($masRecientes !== null): ?>
            <a href="<?= htmlspecialchars(url_con_parametros(['despues' => $masRecientes, 'antes' => null, 'pagina' => null])) ?>">&larr; Más recientes</a>
            <a href="<?= htmlspecialchars(url_con_parametros(['despues' => null, 'antes' => null, 'pagina' => null])) ?>">Ir a lo más reciente</a>
        <?php else: ?>
            <span class="inactivo">&larr; Más recientes</span>
            <span>Estás viendo lo más reciente</span>
        <?php endif; ?>
        <?php if ($masAntiguas !== null): ?>
            <a href="<?= htmlspecialchars(url_con_parametros(['antes' => $masAntiguas, 'despues' => null, 'pagina' => null])) ?>">Más antiguas &rarr;</a>
        <?php else: ?>
            <span class="inactivo">Más antiguas &rarr;</span>
        <?php endif; ?>
    </div>
<?php endif; ?>
