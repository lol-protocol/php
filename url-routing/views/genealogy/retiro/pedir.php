<?php
/* Expects $tipo, $id, $motivos (key => label), $errores (field => message) and $valores (what was sent, to fill the form again). */
$valor = static fn(string $campo): string => (string)($valores[$campo] ?? '');
$error = static fn(string $campo): string => isset($errores[$campo])
    ? '<p class="meta" id="error-' . esc($campo) . '" role="alert">' . esc($errores[$campo]) . '</p>' : '';
?>
<?php ob_start(); ?>
<h1>Pedir el retiro de una página</h1>
<p class="sub">Página: <a href="<?= esc(enlace($tipo, $id)) ?>"><?= esc(enlace($tipo, $id)) ?></a></p>
<p>Si esta página habla de ti o de un familiar fallecido y te afecta, o crees que infringe los términos de uso, cuéntanos qué
hay que retirar y por qué. No necesitas una cuenta. Lo revisa una persona: mientras lo revisa puede ocultar la página, y te
responde al correo que dejes con lo que decidió.</p>
<form action="<?= esc('/r/' . $id . '/') ?>" method="post">
    <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
    <fieldset>
        <legend>Motivo</legend>
<?php foreach ($motivos as $clave => $etiqueta): ?>
        <p><label><input type="radio" name="motivo" value="<?= esc($clave) ?>"<?= $valor('motivo') === $clave ? ' checked' : '' ?> required> <?= esc($etiqueta) ?></label></p>
<?php endforeach; ?>
        <?= $error('motivo') ?>
    </fieldset>
    <div style="position:absolute;left:-10000px" aria-hidden="true">
        <label>No rellenes este campo <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
    </div>
    <p><label for="detalle">Qué hay que retirar y por qué</label><br>
        <textarea id="detalle" name="detalle" rows="6" cols="60" maxlength="2000" required<?= isset($errores['detalle']) ? ' aria-describedby="error-detalle"' : '' ?>><?= esc($valor('detalle')) ?></textarea></p>
    <?= $error('detalle') ?>
    <p><label for="contacto">Tu correo, para responderte</label><br>
        <input id="contacto" type="email" name="contacto" maxlength="254" required value="<?= esc($valor('contacto')) ?>"<?= isset($errores['contacto']) ? ' aria-describedby="error-contacto"' : '' ?>></p>
    <?= $error('contacto') ?>
    <p class="meta">Guardamos tu correo y lo que escribas sólo para responder a este pedido.</p>
    <button type="submit">Enviar el pedido</button>
</form>
<?php $content = ob_get_clean(); $title = 'Pedir retiro'; $noindex = true; include __DIR__ . '/../_layout.php';
