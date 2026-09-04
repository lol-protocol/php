<?php

/** @var array $clientes */
/** @var string|null $error */
/** @var array $valores */

$hoy = date('Y-m-d');
$vencimientoDefault = date('Y-m-d', strtotime('+30 days'));
?>

<h1>Nueva boleta</h1>
<p class="subtitulo"><a href="?page=cobros">&larr; Volver a Cobros e ingresos</a></p>

<div class="panel">
    <form class="form-alta" method="post">
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <label for="cliente_id">Cliente</label>
        <select name="cliente_id" id="cliente_id" required>
            <option value="">Seleccioná un cliente...</option>
            <?php foreach ($clientes as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (string) ($valores['cliente_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?> (<?= htmlspecialchars($c['email']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="concepto">Concepto</label>
        <input type="text" name="concepto" id="concepto" required value="<?= htmlspecialchars($valores['concepto'] ?? '') ?>" placeholder="Ej: Suscripcion mensual">

        <label for="monto">Monto (en la moneda del pais del cliente)</label>
        <input type="number" name="monto" id="monto" required min="0.01" step="0.01" value="<?= htmlspecialchars($valores['monto'] ?? '') ?>">

        <label for="fecha_emision">Fecha de emision</label>
        <input type="date" name="fecha_emision" id="fecha_emision" required value="<?= htmlspecialchars($valores['fecha_emision'] ?? $hoy) ?>">

        <label for="fecha_vencimiento">Fecha de vencimiento</label>
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" required value="<?= htmlspecialchars($valores['fecha_vencimiento'] ?? $vencimientoDefault) ?>">

        <button type="submit">Crear boleta</button>
    </form>
</div>
