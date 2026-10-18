<?php ob_start(); ?>
<h1>Envío</h1>
<?php $paso = 1; include __DIR__ . '/_pasos.php'; ?>
<form action="/p/1/" method="post">
    <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
    <p><label for="direccion">¿A dónde enviamos tu compra?</label><br>
        <textarea id="direccion" name="direccion" rows="4" cols="50" minlength="10" maxlength="500" required
            placeholder="Calle y número, colonia, ciudad, código postal, país"><?= esc($direccion) ?></textarea></p>
    <p><button type="submit">Continuar: pago →</button></p>
</form>
<?php $content = ob_get_clean(); $title = 'Envío'; include __DIR__ . '/../_layout.php';
