<?php

use App\Config;
use App\Csrf;

/** @var string|null $error */
/** @var string $next */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · <?= htmlspecialchars(Config::NOMBRE_SISTEMA) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
    <form class="panel login-card" method="post" action="?page=login&next=<?= urlencode($next) ?>">
        <?= Csrf::campo() ?>
        <h1 style="margin-bottom:4px;"><?= htmlspecialchars(Config::NOMBRE_SISTEMA) ?></h1>
        <p class="subtitulo">Ingresá para ver el panel.</p>
        <?php if ($error): ?>
            <p class="login-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required>
        <button type="submit">Ingresar</button>
    </form>
</div>
</body>
</html>
