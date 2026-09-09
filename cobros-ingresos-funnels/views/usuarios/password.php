<?php

use App\Csrf;

/** @var array $usuario */
/** @var string|null $error */
?>

<h1>Cambiar contraseña</h1>
<p class="subtitulo"><a href="?page=usuarios">&larr; Volver a Usuarios</a></p>

<div class="panel">
    <p class="subtitulo">Usuario: <strong><?= htmlspecialchars($usuario['nombre']) ?></strong> (<?= htmlspecialchars($usuario['email']) ?>)</p>
    <form class="form-alta" method="post">
        <?= Csrf::campo() ?>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <label for="password">Contraseña nueva</label>
        <input type="password" name="password" id="password" required minlength="8" autofocus>

        <label for="password_confirmar">Confirmar contraseña nueva</label>
        <input type="password" name="password_confirmar" id="password_confirmar" required minlength="8">

        <button type="submit">Guardar contraseña</button>
    </form>
</div>
