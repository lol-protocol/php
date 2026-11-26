<?php

use App\Csrf;

/** @var array $cliente */
/** @var int $plazoDias */
/** @var string|null $error */
?>

<h1>Anonimizar cliente #<?= (int) $cliente['id'] ?></h1>
<p class="subtitulo"><a href="?page=cliente&amp;id=<?= (int) $cliente['id'] ?>">&larr; Volver a la ficha</a></p>

<div class="panel">
    <?php if ($error !== null): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>
    <p>Se van a <strong>borrar el nombre, el email y la ciudad</strong> de <strong><?= e($cliente['nombre']) ?></strong>
       (<?= e($cliente['email']) ?>), y el nombre y el email de su registro en el funnel, si lo tiene. También se reescribe, sin esos datos, la
       entrada de auditoría de su alta.</p>
    <p>Quedan su país, idioma, género, rango de edad y todas sus boletas, pagos y notas de crédito: las cifras y la segmentación no cambian.
       No se le podrán cargar boletas ni pagos nuevos.</p>
    <p>Se puede <strong>deshacer durante <?= (int) $plazoDias ?> días</strong>. En ese plazo los datos originales siguen guardados aparte; pasado el
       plazo se borran y ya no hay vuelta atrás.</p>
    <form method="post">
        <?= Csrf::campo() ?>
        <label for="confirmar_email">Para confirmar, escribí el email del cliente</label>
        <input type="email" name="confirmar_email" id="confirmar_email" required autocomplete="off">
        <button type="submit" style="background:var(--critical);border-color:var(--critical);color:#fff;padding:9px 16px;border-radius:6px;cursor:pointer;">
            Anonimizar cliente
        </button>
        <a href="?page=cliente&amp;id=<?= (int) $cliente['id'] ?>" style="margin-left:12px;">Cancelar</a>
    </form>
</div>
