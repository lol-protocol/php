<?php

use App\Csrf;

/** @var array $nota */
/** @var string $fecha */
/** @var string $hoy */
/** @var string|null $error */
$devuelta = $nota['fecha_devolucion'] !== null;
?>

<h1>Registrar devolución de la nota #<?= (int) $nota['id'] ?></h1>
<p class="subtitulo"><a href="?page=notas">&larr; Volver a Notas de crédito</a></p>

<div class="panel">
    <table style="margin-bottom:20px;">
        <tr><th>Cliente</th><td><a href="?page=cliente&id=<?= (int) $nota['cliente_id'] ?>"><?= htmlspecialchars($nota['cliente']) ?></a></td></tr>
        <tr><th>Boleta</th><td>Boleta #<?= (int) $nota['boleta_id'] ?></td></tr>
        <tr><th>Motivo</th><td><?= htmlspecialchars($nota['motivo']) ?></td></tr>
        <tr><th>Emitida</th><td><?= htmlspecialchars($nota['fecha']) ?></td></tr>
        <tr><th>Monto a devolver</th><td><?= dinero_en_moneda((float) $nota['monto'], $nota['moneda_codigo']) ?></td></tr>
    </table>

    <?php if ($devuelta): ?>
        <p>Esta devolución ya se registró: la plata se devolvió el <strong><?= htmlspecialchars($nota['fecha_devolucion']) ?></strong>.</p>
    <?php else: ?>
        <p class="subtitulo">Anotá el día en que le devolviste la plata al cliente. No cambia los reportes de cobros (la nota ya los descuenta desde que se emitió): sirve para saber qué devoluciones siguen pendientes.</p>
        <form class="form-alta" method="post">
            <?= Csrf::campo() ?>
            <?php include __DIR__ . '/../_error.php'; ?>

            <label for="fecha_devolucion">Fecha de la devolución</label>
            <input type="date" name="fecha_devolucion" id="fecha_devolucion" required
                   min="<?= htmlspecialchars($nota['fecha']) ?>" max="<?= htmlspecialchars($hoy) ?>"
                   value="<?= htmlspecialchars($fecha) ?>">

            <button type="submit">Registrar devolución</button>
        </form>
    <?php endif; ?>
</div>
