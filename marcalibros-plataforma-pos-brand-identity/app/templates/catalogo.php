<?php
/**
 * @var list<array{category: array<string, mixed>, items: list<array<string, string>>}> $groups
 * @var array<string, list<array<string, mixed>>> $families
 * @var array<string, mixed>|null $current
 * @var list<string> $motifs
 * @var list<array<string, string>> $items
 * @var int $page
 * @var int $pages
 * @var int $total
 * @var int $categoryCount
 * @var int $catalogTotal
 * @var string|null $notice
 */
$familyTitles = ['movimiento literario' => 'Movimientos literarios', 'tipo de texto' => 'Tipos de texto'];
$familySingular = ['movimiento literario' => 'de este movimiento literario', 'tipo de texto' => 'para este tipo de texto'];
$slug = $current === null ? null : (string) $current['slug'];
$pageUrl = fn (int $n): string => $this->url('/', ['categoria' => $slug, 'pagina' => $n > 1 ? $n : null]);
?>
<div class="envoltura catalogo">
  <aside class="indice">
    <details class="indice__caja" open>
      <summary>Categorías</summary>
      <nav aria-label="Categorías">
        <ul class="indice__lista">
          <li><a href="/"<?= $slug === null ? ' aria-current="page"' : '' ?>><span>Todas</span><span class="puntos" aria-hidden="true"></span><span class="cuenta"><?= (int) $catalogTotal ?></span></a></li>
        </ul>
<?php foreach ($families as $family => $categories): ?>
        <h2 class="indice__grupo"><?= $this->e($familyTitles[$family] ?? ucfirst($family)) ?></h2>
        <ul class="indice__lista">
<?php foreach ($categories as $category): ?>
          <li><a href="<?= $this->e($this->url('/', ['categoria' => (string) $category['slug']])) ?>"<?= $slug === $category['slug'] ? ' aria-current="page"' : '' ?>><span><?= $this->e((string) $category['nombre']) ?></span><span class="puntos" aria-hidden="true"></span><span class="cuenta"><?= (int) $category['total'] ?></span></a></li>
<?php endforeach ?>
        </ul>
<?php endforeach ?>
      </nav>
    </details>
  </aside>

  <div class="catalogo__cuerpo">
<?php if ($notice !== null): ?>
    <p class="aviso" role="status"><?= $this->e($notice) ?></p>
<?php endif ?>

<?php if ($current === null): ?>
    <header class="encabezado">
      <h1>Elige un marcalibro</h1>
      <p class="lead"><?= (int) $total ?> diseños en <?= (int) $categoryCount ?> categorías. Descarga el que te guste en SVG o crea uno con tu propia imagen.</p>
    </header>

    <a class="nuevo" href="/crear">
      <span class="nuevo__tira" aria-hidden="true">+</span>
      <span class="nuevo__texto"><strong>Crea el tuyo</strong> con una foto tuya o con un paisaje.</span>
    </a>

<?php foreach ($groups as $group): ?>
    <section class="grupo" aria-labelledby="g-<?= $this->e((string) $group['category']['slug']) ?>">
      <div class="grupo__cabecera">
        <h2 id="g-<?= $this->e((string) $group['category']['slug']) ?>"><?= $this->e((string) $group['category']['nombre']) ?></h2>
        <a href="<?= $this->e($this->url('/', ['categoria' => (string) $group['category']['slug']])) ?>">Ver los <?= (int) $group['category']['total'] ?><span class="solo-lectores"> marcalibros de <?= $this->e((string) $group['category']['nombre']) ?></span></a>
      </div>
      <ul class="rejilla">
<?php foreach ($group['items'] as $item): ?>
        <li class="celda">
          <a href="/marcalibro/<?= $this->e($item['id']) ?>">
            <img class="tira" src="/catalogo/<?= $this->e($item['id']) ?>.svg" width="1400" height="200" alt="<?= $this->e($item['alt']) ?>" loading="lazy" decoding="async">
            <span class="celda__nombre" aria-hidden="true"><?= $this->e($item['motif']) ?></span>
          </a>
        </li>
<?php endforeach ?>
      </ul>
    </section>
<?php endforeach ?>

<?php else: ?>
    <header class="encabezado">
      <h1><?= $this->e((string) $current['nombre']) ?></h1>
      <p class="lead"><?= (int) $total ?> marcalibros <?= $this->e($familySingular[(string) $current['familia']] ?? '') ?>. Motivos: <?= $this->e(implode(', ', array_map('mb_strtolower', $motifs))) ?>.</p>
    </header>

    <ul class="rejilla">
<?php if ($page === 1): ?>
      <li class="celda celda--nueva">
        <a href="/crear">
          <span class="tira tira--nueva"><span>Crea el tuyo</span></span>
          <span class="celda__nombre">Con tu imagen o un paisaje</span>
        </a>
      </li>
<?php endif ?>
<?php foreach ($items as $item): ?>
      <li class="celda">
        <a href="/marcalibro/<?= $this->e($item['id']) ?>">
          <img class="tira" src="/catalogo/<?= $this->e($item['id']) ?>.svg" width="1400" height="200" alt="<?= $this->e($item['alt']) ?>" loading="lazy" decoding="async">
          <span class="celda__nombre" aria-hidden="true"><?= $this->e($item['motif']) ?></span>
        </a>
      </li>
<?php endforeach ?>
    </ul>

<?php if ($pages > 1): ?>
    <nav class="paginas" aria-label="Páginas de <?= $this->e((string) $current['nombre']) ?>">
<?php if ($page > 1): ?>
      <a rel="prev" href="<?= $this->e($pageUrl($page - 1)) ?>">Anterior</a>
<?php else: ?>
      <span class="paginas__inactivo">Anterior</span>
<?php endif ?>
      <span class="paginas__estado">Página <?= (int) $page ?> de <?= (int) $pages ?></span>
<?php if ($page < $pages): ?>
      <a rel="next" href="<?= $this->e($pageUrl($page + 1)) ?>">Siguiente</a>
<?php else: ?>
      <span class="paginas__inactivo">Siguiente</span>
<?php endif ?>
    </nav>
<?php endif ?>
<?php endif ?>
  </div>
</div>
