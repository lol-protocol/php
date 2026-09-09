<?php

use App\Csrf;

/** @var string|null $error */
?>

<h1>Nuevo usuario</h1>
<p class="subtitulo"><a href="?page=usuarios">&larr; Volver a Usuarios</a></p>

<div class="panel">
    <form class="form-alta" method="post">
        <?= Csrf::campo() ?>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <label for="nombre">Nombre</label>
        <input type="text" name="nombre" id="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">

        <label for="email">Email</label>
        <input type="email" name="email" id="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label for="password">Contraseña</label>
        <input type="password" name="password" id="password" required minlength="8">

        <label for="password_confirmar">Confirmar contraseña</label>
        <input type="password" name="password_confirmar" id="password_confirmar" required minlength="8">

        <button type="submit">Crear usuario</button>
    </form>
</div>
