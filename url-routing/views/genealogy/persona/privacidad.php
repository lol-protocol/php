<?php
/* Expects $persona (the owner's view), $marca (personas.viva: true, false or null) and $comoLaVe (the visitor's view of the same persona). */
$descripcion = match ($marca) {
    true => 'Marcada como viva',
    false => 'Marcada como fallecida',
    default => 'Sin marca: se decide por las fechas',
};
$seleccion = $marca === true ? 'si' : ($marca === false ? 'no' : 'deducir');
?>
<?php ob_start(); $activa = 5; include __DIR__ . '/_cabecera.php'; ?>
<p>Una persona viva es privada: quien no es el propietario solo ve «<?= esc(\App\Support\Privacidad::NOMBRE_OCULTO) ?>». Por omisión se
decide por las fechas (nacida hace menos de <?= (int)\App\Support\Privacidad::ANIOS_VIVA ?> años y sin defunción propia); aquí
puedes corregirlo cuando las fechas no lo dicen.</p>
<dl class="ficha">
    <dt>Marca actual</dt><dd><?= esc($descripcion) ?></dd>
    <dt>Lo que ve un visitante</dt>
    <dd><?php if ($comoLaVe !== null && $comoLaVe['oculta']): ?>
        Solo «<?= esc($comoLaVe['nombres']) ?>»: ninguno de sus datos.
    <?php else: ?>
        Su ficha completa.
    <?php endif; ?></dd>
</dl>
<form action="<?= esc(accion('persona', $persona['id'], 5)) ?>" method="post">
    <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
    <fieldset>
        <legend>¿Vive esta persona?</legend>
        <p><label><input type="radio" name="viva" value="si"<?= $seleccion === 'si' ? ' checked' : '' ?>>
            Vive. Se oculta aunque tenga una defunción registrada o haya nacido hace más de <?= (int)\App\Support\Privacidad::ANIOS_VIVA ?> años.</label></p>
        <p><label><input type="radio" name="viva" value="no"<?= $seleccion === 'no' ? ' checked' : '' ?>>
            Falleció. Se muestra aunque nazca reciente o no conste su defunción.</label></p>
        <p><label><input type="radio" name="viva" value="deducir"<?= $seleccion === 'deducir' ? ' checked' : '' ?>>
            Deducirlo de las fechas.</label></p>
    </fieldset>
    <p class="meta">Una persona que solo figura en colecciones privadas se oculta de todos modos, sea cual sea la marca.</p>
    <button type="submit">Guardar</button>
</form>
<?php $content = ob_get_clean(); $title = 'Privacidad · ' . $persona['nombres'] . ' ' . $persona['apellidos']; include __DIR__ . '/../_layout.php';
