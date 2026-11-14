<?php
/* Expects $solicitud, $persona, $accion ('entrega' | 'simular'), $error (string|null), and, without an error, $entrega
 * (id, enlace, clave, vence_en) or $simulacion (a RectificacionRepository::rectificar() result), $campo and $frase. */
use App\Repositories\Genealogy\EntregaRepository;

$volver = '<p><a href="' . esc(cuenta(4)) . '">Volver a las solicitudes</a></p>';
?>
<?php ob_start(); ?>
<h1><?= $accion === 'entrega' ? 'Entrega con clave' : 'Simulación de una corrección' ?> · solicitud n.º <?= esc($solicitud) ?></h1>
<?php $activa = 4; include __DIR__ . '/_nav.php'; ?>
<?php if ($error !== null): ?>
<p role="alert"><strong>No se hizo nada.</strong> <?= esc($error) ?></p>
<?= $volver ?>
<?php elseif ($accion === 'entrega'): ?>
<p>Entrega <?= esc($entrega['id']) ?> para la persona <?= esc($persona) ?>: vigente hasta el <?= esc($entrega['vence_en']) ?>, se abre hasta
<?= esc(EntregaRepository::MAX_VISTAS) ?> veces y se bloquea con <?= esc(EntregaRepository::MAX_INTENTOS) ?> claves malas.</p>
<dl class="ficha">
    <dt>Enlace (mándalo por un medio)</dt><dd><code><?= esc($entrega['enlace']) ?></code></dd>
    <dt>Clave (mándala por <strong>otro</strong> medio)</dt><dd><code><?= esc($entrega['clave']) ?></code></dd>
</dl>
<p><strong>Esta es la única vez que se muestran:</strong> el sitio guarda solo sus hashes y no se pueden recuperar. No recargues la página ni uses
«Atrás» para volver a verlas. La página de la entrega muestra lo que el sitio oculta a un visitante: mándalas solo después de comprobar quién
pide (<code>docs/SOLICITUDES.md</code>). Si se mandan al medio equivocado: <code>php bin/persona.php entrega <?= esc($persona) ?> --revocar</code>.</p>
<?= $volver ?>
<?php else: ?>
<p>Se corregiría <code><?= esc($campo) ?></code> de la persona <?= esc($persona) ?>.</p>
<p><?= esc($frase) ?></p>
<?php if ($simulacion['aviso_grupo'] !== null): ?>
<p><?= esc($simulacion['aviso_grupo']) ?></p>
<?php endif; ?>
<p><strong>Nada cambió.</strong> Para hacerlo: <code>php bin/persona.php rectificar <?= esc($persona) ?> <?= esc($campo) ?>=… --confirmar</code>
(con <code>--suceso=N</code> si es de un suceso y <code>--pedido=N</code> con el número del pedido, si lo hay).</p>
<?= $volver ?>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Solicitudes de datos'; include __DIR__ . '/../_layout.php';
