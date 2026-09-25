<?php

use App\Csrf;

/**
 * Pie de accion compartido por las pantallas de "confirmar anular": si ya
 * esta anulado, un link de vuelta; si no, el form de confirmacion con CSRF.
 *
 * @var bool $anulado
 * @var string $volverHref
 * @var string $volverTexto
 * @var string $botonTexto
 */
?>
<?php if ($anulado): ?>
    <a href="<?= $volverHref ?>"><?= $volverTexto ?></a>
<?php else: ?>
    <form method="post">
        <?= Csrf::campo() ?>
        <button type="submit" style="background:var(--critical);border-color:var(--critical);color:#fff;padding:9px 16px;border-radius:6px;cursor:pointer;">
            <?= $botonTexto ?>
        </button>
        <a href="<?= $volverHref ?>" style="margin-left:12px;">Cancelar</a>
    </form>
<?php endif; ?>
