<?php

use App\Csrf;

/** @var array $clientes */
/** @var array|null $clienteElegido */
/** @var array $boletasCliente */
/** @var string|null $error */

$hoy = date('Y-m-d');
$metodoLabel = ['transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'efectivo' => 'Efectivo'];
?>

<h1>Nuevo pago</h1>
<p class="subtitulo"><a href="?page=pagos">&larr; Volver a Pagos</a></p>

<div class="panel">
    <?php if (!$clienteElegido): ?>
        <form class="form-alta" method="get">
            <input type="hidden" name="page" value="pago-nuevo">
            <label for="cliente_id">Elegí el cliente que paga</label>
            <select name="cliente_id" id="cliente_id" required>
                <option value="">Seleccioná un cliente...</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Continuar</button>
        </form>
    <?php else: ?>
        <p class="subtitulo">
            Cliente: <strong><?= htmlspecialchars($clienteElegido['nombre']) ?></strong>
            (<?= htmlspecialchars($clienteElegido['pais_nombre']) ?>, paga en <?= htmlspecialchars($clienteElegido['moneda_codigo']) ?>)
            &middot; <a href="?page=pago-nuevo">cambiar cliente</a>
        </p>
        <form class="form-alta" method="post">
            <?= Csrf::campo() ?>
            <input type="hidden" name="cliente_id" value="<?= (int) $clienteElegido['id'] ?>">
            <?php if ($error): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <label for="boleta_id">Boleta que paga (opcional)</label>
            <select name="boleta_id" id="boleta_id">
                <option value="">Anticipo / sin boleta asociada</option>
                <?php foreach ($boletasCliente as $b): ?>
                    <option value="<?= (int) $b['id'] ?>">
                        #<?= (int) $b['id'] ?> · <?= htmlspecialchars($b['concepto']) ?> ·
                        <?= money_moneda((float) $b['saldo'], $b['moneda_codigo']) ?> pendiente
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="monto">Monto (en <?= htmlspecialchars($clienteElegido['moneda_codigo']) ?>)</label>
            <input type="number" name="monto" id="monto" required min="0.01" step="0.01">

            <label for="fecha_pago">Fecha de pago</label>
            <input type="date" name="fecha_pago" id="fecha_pago" required value="<?= $hoy ?>">

            <label for="metodo">Metodo</label>
            <select name="metodo" id="metodo" required>
                <?php foreach ($metodoLabel as $clave => $etiqueta): ?>
                    <option value="<?= $clave ?>"><?= $etiqueta ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Registrar pago</button>
        </form>
    <?php endif; ?>
</div>
