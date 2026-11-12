<?php
/* Expects $token (the link's own) and $error (a message, or ''). */
?>
<?php ob_start(); ?>
<h1>Tus datos</h1>
<p>Escribe la clave que recibiste, por un medio distinto al de este enlace, para ver los datos que el sitio tiene de ti.</p>
<form action="<?= esc('/e/' . $token . '/') ?>" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
    <p><label for="clave">Clave</label><br>
        <input id="clave" type="text" name="clave" required maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false"<?= $error !== '' ? ' aria-describedby="error-clave"' : '' ?>></p>
<?php if ($error !== ''): ?>
    <p class="meta" id="error-clave" role="alert"><?= esc($error) ?></p>
<?php endif; ?>
    <button type="submit">Ver mis datos</button>
</form>
<?php $content = ob_get_clean(); $title = 'Tus datos'; $noindex = true; include __DIR__ . '/../_layout.php';
