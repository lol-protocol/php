<?php
/* Expects $tipo, $id, $motivos (key => label), $errores (field => message) and $valores (what was sent, to fill the form again). */
$valor = static fn(string $campo): string => (string)($valores[$campo] ?? '');
$error = static fn(string $campo): string => isset($errores[$campo])
    ? '<p class="meta" id="error-' . esc($campo) . '" role="alert">' . esc($errores[$campo]) . '</p>' : '';
?>
<?php ob_start(); ?>
<h1><?= esc(t('retiro.titulo')) ?></h1>
<p class="sub"><?= esc(t('retiro.pagina')) ?> <a href="<?= esc(enlace($tipo, $id)) ?>"><?= esc(enlace($tipo, $id)) ?></a></p>
<p><?= esc(t('retiro.intro')) ?></p>
<form action="<?= esc('/r/' . $id . '/') ?>" method="post">
    <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
    <fieldset>
        <legend><?= esc(t('retiro.motivo')) ?></legend>
<?php foreach ($motivos as $clave => $etiqueta): ?>
        <p><label><input type="radio" name="motivo" value="<?= esc($clave) ?>"<?= $valor('motivo') === $clave ? ' checked' : '' ?> required> <?= esc(t('retiro.motivo.' . $clave)) ?></label></p>
<?php endforeach; ?>
        <?= $error('motivo') ?>
    </fieldset>
    <div style="position:absolute;left:-10000px" aria-hidden="true">
        <label><?= esc(t('retiro.trampa')) ?> <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
    </div>
    <p><label for="detalle"><?= esc(t('retiro.detalle')) ?></label><br>
        <textarea id="detalle" name="detalle" rows="6" cols="60" maxlength="2000" required<?= isset($errores['detalle']) ? ' aria-describedby="error-detalle"' : '' ?>><?= esc($valor('detalle')) ?></textarea></p>
    <?= $error('detalle') ?>
    <p><label for="contacto"><?= esc(t('retiro.correo')) ?></label><br>
        <input id="contacto" type="email" name="contacto" maxlength="254" required value="<?= esc($valor('contacto')) ?>"<?= isset($errores['contacto']) ? ' aria-describedby="error-contacto"' : '' ?>></p>
    <?= $error('contacto') ?>
    <p class="meta"><?= esc(t('retiro.guardamos')) ?></p>
    <button type="submit"><?= esc(t('retiro.enviar')) ?></button>
</form>
<?php $content = ob_get_clean(); $title = t('retiro.title'); $noindex = true; include __DIR__ . '/../_layout.php';
