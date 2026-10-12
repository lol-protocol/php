<?php
/**
 * @var array<string, string> $item
 * @var list<string> $colors
 * @var list<string> $authors
 * @var string|null $previous
 * @var string|null $next
 * @var int $position
 * @var int $inCategory
 * @var int $listPage
 */
$backUrl = $this->url('/', ['categoria' => $item['categorySlug'], 'pagina' => $listPage > 1 ? $listPage : null]);
?>
<div class="envoltura detalle">
  <p class="migas"><a href="<?= $this->e($backUrl) ?>">Volver a <?= $this->e($item['category']) ?></a></p>

  <h1><?= $this->e($item['motif']) ?></h1>
  <p class="lead">Número <?= (int) $position ?> de <?= (int) $inCategory ?> en <?= $this->e($item['category']) ?>.</p>

  <img class="tira tira--grande" src="/catalogo/<?= $this->e($item['id']) ?>.svg" width="1400" height="200" alt="<?= $this->e($item['alt']) ?>">

  <div class="detalle__fila">
    <section aria-labelledby="colores">
      <h2 id="colores">Colores</h2>
      <ul class="muestras">
<?php foreach ($colors as $hex): ?>
        <li><span class="muestra <?= $this->e($this->colorClass($hex)) ?>" aria-hidden="true"></span><span><?= $this->e($this->colorName($hex)) ?> <code><?= $this->e($hex) ?></code></span></li>
<?php endforeach ?>
      </ul>
<?php if ($authors !== []): ?>
      <h2>Inspirado en</h2>
      <p>La obra de <?= $this->e(implode(', ', $authors)) ?>.</p>
<?php endif ?>
    </section>

    <section aria-labelledby="descarga">
      <h2 id="descarga">Descargar</h2>
      <p>Archivo SVG: se ve nítido a cualquier tamaño, y pesa menos de 10 KB. La proporción es 7:1 (1400 × 200 px de base).</p>
      <p class="acciones">
        <a class="boton" href="/catalogo/<?= $this->e($item['id']) ?>.svg?descargar=1" download>Descargar SVG</a>
        <a class="boton boton--claro" href="/crear">Crear uno con mi imagen</a>
      </p>
    </section>
  </div>

  <nav class="paginas" aria-label="Marcalibros de <?= $this->e($item['category']) ?>">
<?php if ($previous !== null): ?>
    <a rel="prev" href="/marcalibro/<?= $this->e($previous) ?>">Anterior</a>
<?php else: ?>
    <span class="paginas__inactivo">Anterior</span>
<?php endif ?>
    <span class="paginas__estado"><?= (int) $position ?> de <?= (int) $inCategory ?></span>
<?php if ($next !== null): ?>
    <a rel="next" href="/marcalibro/<?= $this->e($next) ?>">Siguiente</a>
<?php else: ?>
    <span class="paginas__inactivo">Siguiente</span>
<?php endif ?>
  </nav>
</div>
