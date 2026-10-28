<?php
/* Expects $pedidos (RetiroRepository::todos()). */
use App\Repositories\Genealogy\RetiroRepository;

$estados = ['pendiente' => 'Pendiente', 'oculto' => 'Oculta mientras se revisa', 'retirado' => 'Retirada', 'rechazado' => 'Rechazado: la página se muestra'];
$decisiones = ['ocultar' => 'Ocultar mientras reviso', 'retirar' => 'Retirar', 'rechazar' => 'Rechazar el pedido'];
?>
<?php ob_start(); ?>
<h1>Pedidos de retiro</h1>
<?php $activa = 3; include __DIR__ . '/_nav.php'; ?>
<p>Lo que pidieron retirar desde el enlace «Pedir el retiro de esta página». Una página oculta o retirada responde «Página
retirada» a quien no es el propietario; tú la sigues viendo. Cada decisión queda registrada con su fecha y tu nota.</p>
<?php if ($pedidos === []): ?>
<p class="vacio">No hay pedidos.</p>
<?php endif; ?>
<?php foreach ($pedidos as $p): ?>
<section>
<?php $dias = intdiv(time() - (int)strtotime((string)$p['creado_en']), 86400); ?>
    <h2>Pedido n.º <?= esc($p['id']) ?> · <?= esc($estados[$p['estado']] ?? $p['estado']) ?><?php if ($p['estado'] === 'pendiente'): ?>
        <span class="meta">· <?= $dias === 0 ? 'llegó hoy' : ($dias === 1 ? 'espera hace 1 día' : 'espera hace ' . $dias . ' días') ?></span><?php endif; ?></h2>
    <dl class="ficha">
        <dt>Página</dt><dd><a href="<?= esc(enlace($p['tipo'], $p['recurso_id'])) ?>"><?= esc(enlace($p['tipo'], $p['recurso_id'])) ?></a></dd>
        <dt>Motivo</dt><dd><?= esc(RetiroRepository::MOTIVOS[$p['motivo']] ?? $p['motivo']) ?></dd>
<?php if (!empty($p['depurado_en'])): ?>
        <dt>Datos personales</dt><dd>Borrados el <?= esc(substr((string)$p['depurado_en'], 0, 10)) ?> (el correo y lo que escribió quien pidió).</dd>
<?php else: ?>
        <dt>Detalle</dt><dd><?= nl2br(esc($p['detalle'])) ?></dd>
<?php if (!empty($p['marcas'])): ?>
        <dt>El filtro encontró</dt><dd>⚠ <?= esc($p['marcas']) ?></dd>
<?php endif; ?>
        <dt>Responder a</dt><dd><?= esc($p['contacto']) ?></dd>
<?php endif; ?>
        <dt>Historial</dt><dd><ul class="lista">
<?php foreach ($p['eventos'] as $e): ?>
            <li><?= esc($e['ocurrido_en']) ?> · <?= esc($e['estado']) ?><?= $e['nota'] !== null && $e['nota'] !== '' ? ' · ' . esc($e['nota']) : '' ?></li>
<?php endforeach; ?>
        </ul></dd>
    </dl>
    <form action="<?= esc(cuenta(3)) ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= esc(get_csrf_token()) ?>">
        <input type="hidden" name="pedido" value="<?= esc($p['id']) ?>">
        <p><label>Nota (queda en el historial)<br><textarea name="nota" rows="2" cols="60" maxlength="2000"></textarea></label></p>
        <p>
<?php foreach ($decisiones as $clave => $etiqueta): ?>
            <button type="submit" name="decision" value="<?= esc($clave) ?>"><?= esc($etiqueta) ?></button>
<?php endforeach; ?>
        </p>
    </form>
</section>
<?php endforeach; ?>
<?php $content = ob_get_clean(); $title = 'Pedidos de retiro'; include __DIR__ . '/../_layout.php';
