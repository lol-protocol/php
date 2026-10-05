<?php

use App\Csrf;
use App\EnvioUnico;
use App\Etiquetas;
use App\Repositories\ClienteRepository;

/** @var array $clientes */
/** @var bool $clientesTruncados */
/** @var array|null $clienteElegido */
/** @var array $boletasCliente */
/** @var string|null $error */
/** @var array $valores */

$hoy = date('Y-m-d');
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
            <?php if ($clientesTruncados): ?>
                <p class="nota">Se muestran los primeros <?= ClienteRepository::LIMITE_SELECTOR ?> clientes por nombre. Para registrar el pago de otro, buscalo en <a href="?page=clientes">Clientes</a> y usá «Registrar pago» desde su ficha.</p>
            <?php endif; ?>
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
            <?= EnvioUnico::campo() ?>
            <input type="hidden" name="cliente_id" value="<?= (int) $clienteElegido['id'] ?>">
            <?php include __DIR__ . '/../_error.php'; ?>

            <label for="boleta_id">Boleta que paga (opcional)</label>
            <select name="boleta_id" id="boleta_id">
                <option value="">Anticipo / sin boleta asociada</option>
                <?php foreach ($boletasCliente as $b): ?>
                    <?php if ($b['anulada'] || $b['saldo'] <= 0.01): continue; endif; ?>
                    <option value="<?= (int) $b['id'] ?>" <?= (string) ($valores['boleta_id'] ?? '') === (string) $b['id'] ? 'selected' : '' ?>>
                        #<?= (int) $b['id'] ?> · <?= htmlspecialchars($b['concepto']) ?> ·
                        <?= money_moneda((float) $b['saldo'], $b['moneda_codigo']) ?> pendiente
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="monto">Monto (en <?= htmlspecialchars($clienteElegido['moneda_codigo']) ?>)</label>
            <input type="number" name="monto" id="monto" required min="0.01" step="0.01" value="<?= htmlspecialchars($valores['monto'] ?? '') ?>">

            <label for="fecha_pago">Fecha de pago</label>
            <input type="date" name="fecha_pago" id="fecha_pago" required value="<?= htmlspecialchars($valores['fecha_pago'] ?? $hoy) ?>">

            <label for="metodo">Metodo</label>
            <select name="metodo" id="metodo" required>
                <?php foreach (Etiquetas::metodosPago() as $clave => $etiqueta): ?>
                    <option value="<?= $clave ?>" <?= ($valores['metodo'] ?? '') === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Registrar pago</button>
        </form>
    <?php endif; ?>
</div>
