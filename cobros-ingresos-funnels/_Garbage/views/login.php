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
    <!--
      Estas clases son solo de esta pantalla: se sacaron de assets/style.css
      (que es el que sigue sirviendo el app en vivo) cuando login se movio
      a _Garbage, para no dejar CSS muerta en un archivo activo. Si esta
      pantalla se reactiva alguna vez, sigue viniendo con su estilo.
    -->
    <style>
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            width: 100%;
            max-width: 360px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .login-card label { font-size: 13px; color: var(--text-secondary); margin-top: 10px; }

        .login-card input {
            padding: 9px 10px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--page);
            color: var(--text-primary);
            font-size: 14px;
        }

        .login-card button {
            margin-top: 18px;
            padding: 10px 14px;
            border-radius: 6px;
            border: 1px solid var(--series-1);
            background: var(--series-1);
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }

        .login-error {
            background: rgba(208, 59, 59, 0.12);
            color: var(--critical);
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 13px;
            margin: 0;
        }
    </style>
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
