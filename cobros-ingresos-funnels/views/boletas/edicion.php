<?php

use App\Csrf;
use App\Validacion;

/** @var array $boleta */
/** @var string|null $error */
?>

<h1>Editar boleta #<?= (int) $boleta['id'] ?></h1>
<p class="subtitulo"><a href="?page=cobros">&larr; Volver a Cobros e ingresos</a></p>

<div class="panel">
    <p class="subtitulo">Cliente: <strong><?= e($boleta['cliente']) ?></strong> (no se puede reasignar desde acá)</p>
    <form class="form-alta" method="post">
        <?= Csrf::campo() ?>
        <?php include __DIR__ . '/../_error.php'; ?>

        <label for="concepto">Concepto</label>
        <input type="text" name="concepto" id="concepto" required maxlength="<?= Validacion::MAX_CONCEPTO ?>" value="<?= e($boleta['concepto']) ?>">

        <label for="monto">Monto (<?= e($boleta['moneda_codigo']) ?>)</label>
        <?= campo_monto($boleta['monto'], $boleta['moneda_codigo']) ?>

        <label for="fecha_emision">Fecha de emisión</label>
        <input type="date" name="fecha_emision" id="fecha_emision" required value="<?= e($boleta['fecha_emision']) ?>">

        <label for="fecha_vencimiento">Fecha de vencimiento</label>
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" required value="<?= e($boleta['fecha_vencimiento']) ?>">

        <button type="submit">Guardar cambios</button>
    </form>
</div>
