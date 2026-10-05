<?php

use App\Csrf;
use App\EnvioUnico;
use App\Repositories\ClienteRepository;
use App\Validacion;

/** @var array $clientes */
/** @var bool $clientesTruncados */
/** @var string|null $error */
/** @var array $valores */

$hoy = date('Y-m-d');
$vencimientoDefault = date('Y-m-d', strtotime('+30 days'));
?>

<h1>Nueva boleta</h1>
<p class="subtitulo"><a href="?page=cobros">&larr; Volver a Cobros e ingresos</a></p>

<div class="panel">
    <form class="form-alta" method="post">
        <?= Csrf::campo() ?>
        <?= EnvioUnico::campo() ?>
        <?php include __DIR__ . '/../_error.php'; ?>

        <label for="cliente_id">Cliente</label>
        <select name="cliente_id" id="cliente_id" required>
            <option value="">Seleccioná un cliente...</option>
            <?php foreach ($clientes as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (string) ($valores['cliente_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?> (<?= htmlspecialchars($c['email']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($clientesTruncados): ?>
            <p class="nota">Se muestran los primeros <?= ClienteRepository::LIMITE_SELECTOR ?> clientes por nombre. Para cargar una boleta a otro, buscalo en <a href="?page=clientes">Clientes</a> y usá «Nueva boleta» desde su ficha.</p>
        <?php endif; ?>

        <label for="concepto">Concepto</label>
        <input type="text" name="concepto" id="concepto" required maxlength="<?= Validacion::MAX_CONCEPTO ?>" value="<?= htmlspecialchars($valores['concepto'] ?? '') ?>" placeholder="Ej: Suscripcion mensual">

        <label for="monto">Monto (en la moneda del pais del cliente)</label>
        <input type="number" name="monto" id="monto" required min="0.01" step="0.01" value="<?= htmlspecialchars($valores['monto'] ?? '') ?>">

        <label for="fecha_emision">Fecha de emision</label>
        <input type="date" name="fecha_emision" id="fecha_emision" required value="<?= htmlspecialchars($valores['fecha_emision'] ?? $hoy) ?>">

        <label for="fecha_vencimiento">Fecha de vencimiento</label>
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" required value="<?= htmlspecialchars($valores['fecha_vencimiento'] ?? $vencimientoDefault) ?>">

        <button type="submit">Crear boleta</button>
    </form>
</div>
