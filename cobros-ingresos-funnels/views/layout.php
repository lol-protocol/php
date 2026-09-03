<?php
/** @var string $content */
/** @var string $activePage */
/** @var string $titulo */

use App\Config;

$paginas = [
    'dashboard' => 'Dashboard',
    'cobros' => 'Cobros e ingresos',
    'pagos' => 'Pagos',
    'funnel' => 'Funnel',
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> · <?= htmlspecialchars(Config::NOMBRE_SISTEMA) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="brand"><?= htmlspecialchars(Config::NOMBRE_SISTEMA) ?></div>
    <nav class="tabs">
        <?php foreach ($paginas as $clave => $etiqueta): ?>
            <a href="?page=<?= $clave ?>" class="<?= $activePage === $clave ? 'active' : '' ?>"><?= $etiqueta ?></a>
        <?php endforeach; ?>
    </nav>
</header>
<main>
    <?= $content ?>
</main>
<footer>Datos de ejemplo generados localmente &middot; <?= htmlspecialchars(Config::NOMBRE_SISTEMA) ?></footer>
</body>
</html>
