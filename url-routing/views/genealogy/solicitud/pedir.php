<?php
/* Expects $id (the persona), $derechos (key => label), $errores (field => message) and $valores (what was sent, to fill the form again). */
$valor = static fn(string $campo): string => (string)($valores[$campo] ?? '');
$error = static fn(string $campo): string => isset($errores[$campo])
    ? '<p class="meta" id="error-' . esc($campo) . '" role="alert">' . esc($errores[$campo]) . '</p>' : '';
?>
<?php ob_start(); ?>
<h1>Pedir ver, corregir o borrar los datos de una persona</h1>
<p class="sub">Persona: <a href="<?= esc(enlace('persona', $id)) ?>"><?= esc(enlace('persona', $id)) ?></a></p>
<p>Si esta persona eres tú (o, si ya falleció, eres de su familia), puedes pedir ver qué datos tiene el sitio, que se corrija
alguno o que se borren. No necesitas una cuenta. Lo revisa una persona.</p>
<p><strong>Esto no entrega, cambia ni borra nada por sí solo.</strong> Antes de hacerlo, una persona te escribirá al correo que dejes
y te pedirá un dato que solo tú sabrías, para comprobar que eres quien dice.</p>
<p><a href="<?= esc('/r/' . $id . '/') ?>">¿Lo que quieres es que se oculte una página? Pide su retiro</a></p>
<form action="<?= esc('/s/' . $id . '/') ?>" method="post">
    <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
    <fieldset>
        <legend>Qué quieres pedir</legend>
<?php foreach ($derechos as $clave => $etiqueta): ?>
        <p><label><input type="radio" name="derecho" value="<?= esc($clave) ?>"<?= $valor('derecho') === $clave ? ' checked' : '' ?> required> <?= esc($etiqueta) ?></label></p>
<?php endforeach; ?>
        <?= $error('derecho') ?>
    </fieldset>
    <div style="position:absolute;left:-10000px" aria-hidden="true">
        <label>No rellenes este campo <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
    </div>
    <p><label for="detalle">Qué datos y de qué persona</label><br>
        <textarea id="detalle" name="detalle" rows="6" cols="60" maxlength="2000" required<?= isset($errores['detalle']) ? ' aria-describedby="error-detalle"' : '' ?>><?= esc($valor('detalle')) ?></textarea></p>
    <?= $error('detalle') ?>
    <p><label for="contacto">Tu correo, para responderte</label><br>
        <input id="contacto" type="email" name="contacto" maxlength="254" required value="<?= esc($valor('contacto')) ?>"<?= isset($errores['contacto']) ? ' aria-describedby="error-contacto"' : '' ?>></p>
    <?= $error('contacto') ?>
    <p class="meta">Guardamos tu correo y lo que escribas solo para responder a esta solicitud. No pedimos ningún documento.</p>
    <button type="submit">Enviar la solicitud</button>
</form>
<?php $content = ob_get_clean(); $title = 'Pedir mis datos'; $noindex = true; include __DIR__ . '/../_layout.php';
