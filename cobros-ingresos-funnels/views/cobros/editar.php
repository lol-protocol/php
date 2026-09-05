<?php

/** @var array $boleta */
/** @var string|null $error */
?>

<h1>Editar boleta #<?= (int) $boleta['id'] ?></h1>
<p class="subtitulo"><a href="?page=cobros">&larr; Volver a Cobros e ingresos</a></p>

<div class="panel">
    <p class="subtitulo">Cliente: <strong><?= htmlspecialchars($boleta['cliente']) ?></strong> (no se puede reasignar desde acá)</p>
    <form class="form-alta" method="post">
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <label for="concepto">Concepto</label>
        <input type="text" name="concepto" id="concepto" required value="<?= htmlspecialchars($boleta['concepto']) ?>">

        <label for="monto">Monto (<?= htmlspecialchars($boleta['moneda_codigo']) ?>)</label>
        <input type="number" name="monto" id="monto" required min="0.01" step="0.01" value="<?= htmlspecialchars((string) $boleta['monto']) ?>">

        <label for="fecha_emision">Fecha de emision</label>
        <input type="date" name="fecha_emision" id="fecha_emision" required value="<?= htmlspecialchars($boleta['fecha_emision']) ?>">

        <label for="fecha_vencimiento">Fecha de vencimiento</label>
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" required value="<?= htmlspecialchars($boleta['fecha_vencimiento']) ?>">

        <button type="submit">Guardar cambios</button>
    </form>
</div>
