<?php
/* Expects $solicitudes (SolicitudRepository::todas()). */
use App\Repositories\Genealogy\RetiroRepository;
use App\Repositories\Genealogy\SolicitudRepository;

$estados = ['pendiente' => 'Pendiente', 'atendida' => 'Atendida', 'rechazada' => 'Rechazada'];
$decisiones = ['atender' => 'Marcar como atendida', 'rechazar' => 'Rechazar'];
?>
<?php ob_start(); ?>
<h1>Solicitudes de datos</h1>
<?php $activa = 4; include __DIR__ . '/_nav.php'; ?>
<p>Lo que pidieron desde el enlace «Pedir ver, corregir o borrar mis datos» del pie de la página de una persona. <strong>Recibir una
solicitud no entrega, cambia ni borra nada.</strong> Antes de contestar, comprueba quién pide (<code>docs/SOLICITUDES.md</code>);
cerrarla aquí solo la marca. Lo demás se hace con <code>bin/persona.php</code>.</p>
<?php if ($solicitudes === []): ?>
<p class="vacio">No hay solicitudes.</p>
<?php endif; ?>
<?php
$cerradas = count(array_filter($solicitudes, static fn(array $s): bool => $s['estado'] !== 'pendiente'));
if ($cerradas >= SolicitudRepository::CERRADAS_VISIBLES): ?>
<p class="meta">Se muestran las <?= SolicitudRepository::CERRADAS_VISIBLES ?> solicitudes cerradas más recientes; las pendientes salen todas.</p>
<?php endif; ?>
<?php foreach ($solicitudes as $s): ?>
<section>
<?php $dias = intdiv(time() - (int)strtotime((string)$s['creado_en']), 86400); ?>
    <h2>Solicitud n.º <?= esc($s['id']) ?> · <?= esc($estados[$s['estado']] ?? $s['estado']) ?><?php if ($s['estado'] === 'pendiente'): ?>
        <span class="meta">· <?= $dias === 0 ? 'llegó hoy' : ($dias === 1 ? 'espera hace 1 día' : 'espera hace ' . $dias . ' días') ?></span><?php endif; ?></h2>
    <dl class="ficha">
        <dt>Persona</dt><dd><a href="<?= esc(enlace('persona', $s['persona_id'])) ?>"><?= esc(enlace('persona', $s['persona_id'])) ?></a></dd>
        <dt>Pide</dt><dd><?= esc(SolicitudRepository::DERECHOS[$s['derecho']] ?? $s['derecho']) ?></dd>
<?php if (!empty($s['depurado_en'])): ?>
        <dt>Datos personales</dt><dd>Borrados el <?= esc(substr((string)$s['depurado_en'], 0, 10)) ?> (el correo, lo que escribió quien pidió y tu nota).</dd>
<?php else: ?>
        <dt>Detalle</dt><dd><?= nl2br(esc($s['detalle'])) ?></dd>
        <dt>Responder a</dt><dd><?= esc(RetiroRepository::enmascarar($s['contacto'])) ?>
            <details><summary>Mostrar el correo completo</summary><?= esc($s['contacto']) ?></details></dd>
<?php endif; ?>
<?php if ($s['estado'] === 'pendiente'): ?>
        <dt>Para contestar</dt><dd><code>php bin/persona.php resumen <?= esc($s['persona_id']) ?></code> (borrador del resumen) ·
            <code>php bin/persona.php acceso <?= esc($s['persona_id']) ?></code></dd>
<?php else: ?>
        <dt>Cerrada</dt><dd><?= esc(substr((string)$s['cerrada_en'], 0, 16)) ?><?= !empty($s['nota']) ? ' · ' . esc($s['nota']) : '' ?></dd>
<?php endif; ?>
    </dl>
<?php if ($s['estado'] === 'pendiente'): ?>
    <form action="<?= esc(cuenta(4)) ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
        <input type="hidden" name="solicitud" value="<?= esc($s['id']) ?>">
        <p><label>Nota (para tus registros: qué hiciste, <strong>no</strong> los datos)<br><textarea name="nota" rows="2" cols="60" maxlength="2000"></textarea></label></p>
        <p>
<?php foreach ($decisiones as $clave => $etiqueta): ?>
            <button type="submit" name="decision" value="<?= esc($clave) ?>"><?= esc($etiqueta) ?></button>
<?php endforeach; ?>
        </p>
    </form>
<?php endif; ?>
</section>
<?php endforeach; ?>
<?php $content = ob_get_clean(); $title = 'Solicitudes de datos'; include __DIR__ . '/../_layout.php';
