<?php

/** @var array $usuarios */
/** @var int|null $usuarioActualId */
?>

<h1>Usuarios</h1>
<p class="subtitulo">Quién puede entrar al panel. Revocar no borra al usuario, solo le apaga el acceso.</p>
<p class="subtitulo"><a href="?page=usuario-nuevo">+ Nuevo usuario</a></p>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Nombre</th><th>Email</th><th>Alta</th><th>Estado</th><th>&nbsp;</th></tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['nombre']) ?><?= $u['id'] === $usuarioActualId ? ' (vos)' : '' ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars(substr($u['creado_en'], 0, 10)) ?></td>
                    <td>
                        <?php if ($u['activo']): ?>
                            <span class="badge pagada">Activo</span>
                        <?php else: ?>
                            <span class="badge anulada">Revocado</span>
                        <?php endif; ?>
                    </td>
                    <td class="acciones">
                        <a href="?page=usuario-password&id=<?= (int) $u['id'] ?>">Cambiar contraseña</a>
                        <?php if ($u['id'] !== $usuarioActualId): ?>
                            <a href="?page=usuario-revocar&id=<?= (int) $u['id'] ?>"><?= $u['activo'] ? 'Revocar' : 'Reactivar' ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
