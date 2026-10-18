<?php ob_start(); ?>
<h1>Confirmar</h1>
<?php $paso = 3; include __DIR__ . '/_pasos.php'; ?>
<?php include __DIR__ . '/_lineas.php'; ?>
<dl class="ficha">
    <dt>Enviar a</dt><dd><?= esc($direccion) ?> <a href="/p/1/">cambiar</a></dd>
    <dt>Pago</dt><dd>Pendiente: te contactamos para coordinarlo</dd>
</dl>
<form action="/p/3/" method="post">
    <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
    <p><button type="submit">Confirmar compra</button></p>
</form>
<?php $content = ob_get_clean(); $title = 'Confirmar'; include __DIR__ . '/../_layout.php';
