<?php

use App\Csrf;

/** @var array $usuario */
/** @var bool $esUnoMismo */

$reactivando = !$usuario['activo'];
?>

<h1><?= $reactivando ? 'Reactivar' : 'Revocar' ?> acceso</h1>
<p class="subtitulo"><a href="?page=usuarios">&larr; Volver a Usuarios</a></p>

<div class="panel">
    <table style="margin-bottom:20px;">
        <tr><th>Nombre</th><td><?= htmlspecialchars($usuario['nombre']) ?></td></tr>
        <tr><th>Email</th><td><?= htmlspecialchars($usuario['email']) ?></td></tr>
    </table>

    <?php if ($esUnoMismo): ?>
        <p class="error">No podés <?= $reactivando ? 'reactivar' : 'revocar' ?> tu propio acceso.</p>
    <?php elseif ($reactivando): ?>
        <p>¿Reactivar el acceso de este usuario? Va a poder volver a entrar al panel con su email y contraseña.</p>
        <form method="post">
            <?= Csrf::campo() ?>
            <button type="submit" style="padding:9px 16px;border-radius:6px;cursor:pointer;">Si, reactivar acceso</button>
            <a href="?page=usuarios" style="margin-left:12px;">Cancelar</a>
        </form>
    <?php else: ?>
        <p>¿Seguro que querés revocar el acceso de este usuario? No se borra: queda marcado como revocado
           y deja de poder loguearse, pero su historial en Auditoría se conserva.</p>
        <form method="post">
            <?= Csrf::campo() ?>
            <button type="submit" style="background:var(--critical);border-color:var(--critical);color:#fff;padding:9px 16px;border-radius:6px;cursor:pointer;">
                Si, revocar acceso
            </button>
            <a href="?page=usuarios" style="margin-left:12px;">Cancelar</a>
        </form>
    <?php endif; ?>
</div>
