<?php
/**
 * @var string       $content
 * @var string       $title
 * @var bool         $private
 * @var list<string> $scripts
 * @var string       $section
 * @var string       $description
 * @var int|null     $retentionDays  Lo aporta View como dato compartido.
 */
$nav = ['catalogo' => ['/', 'Catálogo'], 'crear' => ['/crear', 'Crear el mío']];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $this->e($title) ?></title>
<?php if ($description !== ''): ?>
<meta name="description" content="<?= $this->e($description) ?>">
<?php endif ?>
<?php if ($private): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif ?>
<meta name="color-scheme" content="light">
<link rel="icon" href="/marca.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= $this->e($this->asset('app.css')) ?>">
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<header class="cabecera">
  <div class="cabecera__fila">
    <a class="marca" href="/">
      <img src="/marca.svg" alt="" width="32" height="32">
      <span>Marcalibros</span>
    </a>
    <nav class="menu" aria-label="Principal">
<?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= $this->e($href) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a>
<?php endforeach ?>
    </nav>
  </div>
</header>
<main id="contenido" tabindex="-1">
<?= $content ?>
</main>
<footer class="pie">
  <div class="pie__fila">
    <p>Los marcalibros que creas se guardan <?= isset($retentionDays) ? (int) $retentionDays . ' días' : 'unos días' ?> y se borran solos. No hace falta cuenta: quien tenga el enlace de tu marcalibro puede verlo y borrarlo.</p>
    <p>Hecho con la identidad de Plataforma POS.</p>
  </div>
</footer>
<?php foreach ($scripts as $script): ?>
<script src="<?= $this->e($this->asset($script)) ?>" defer></script>
<?php endforeach ?>
</body>
</html>
