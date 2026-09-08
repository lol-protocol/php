<?php

use App\Csrf;

/** @var array $pago */
/** @var string|null $error */

$metodoLabel = ['transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'efectivo' => 'Efectivo'];
?>

<h1>Editar pago #<?= (int) $pago['id'] ?></h1>
<p class="subtitulo"><a href="?page=pagos">&larr; Volver a Pagos</a></p>

<div class="panel">
    <p class="subtitulo">
        Cliente: <strong><?= htmlspecialchars($pago['cliente']) ?></strong>
        &middot; <?= $pago['boleta_id'] ? 'Boleta #' . (int) $pago['boleta_id'] : 'Anticipo' ?>
        (no se puede reasignar desde acá)
    </p>
    <form class="form-alta" method="post">
        <?= Csrf::campo() ?>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <label for="monto">Monto (<?= htmlspecialchars($pago['moneda_codigo']) ?>)</label>
        <input type="number" name="monto" id="monto" required min="0.01" step="0.01" value="<?= htmlspecialchars((string) $pago['monto']) ?>">

        <label for="fecha_pago">Fecha de pago</label>
        <input type="date" name="fecha_pago" id="fecha_pago" required value="<?= htmlspecialchars($pago['fecha_pago']) ?>">

        <label for="metodo">Metodo</label>
        <select name="metodo" id="metodo" required>
            <?php foreach ($metodoLabel as $clave => $etiqueta): ?>
                <option value="<?= $clave ?>" <?= $pago['metodo'] === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Guardar cambios</button>
    </form>
</div>
