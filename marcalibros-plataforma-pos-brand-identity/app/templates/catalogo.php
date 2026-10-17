<?php
/**
 * @var list<array{category: array<string, mixed>, items: list<array<string, string>>}> $groups
 * @var array<string, list<array<string, mixed>>> $families
 * @var array<string, mixed>|null $current
 * @var string|null $heading Título de la lista (categoría o resultados de un filtro); null en la portada.
 * @var string|null $lead
 * @var array{motivo: string|null, color: string|null} $filters
 * @var array<string, array<string, string>> $motifOptions Motivos agrupados por categoría.
 * @var array<string, string> $colorOptions color => nombre para mostrar
 * @var list<array<string, string>> $items
 * @var int $page
 * @var int $pages
 * @var int $total
 * @var int $categoryCount
 * @var int $catalogTotal
 * @var string|null $notice
 */
$familyTitles = ['movimiento literario' => 'Movimientos literarios', 'tipo de texto' => 'Tipos de texto'];
$slug = $current === null ? null : (string) $current['slug'];
$filtering = $filters['motivo'] !== null || $filters['color'] !== null;
$pageUrl = fn (int $n): string => $this->url('/', ['categoria' => $slug, 'motivo' => $filters['motivo'], 'color' => $filters['color'], 'pagina' => $n > 1 ? $n : null]);
?>
<div class="envoltura catalogo">
  <aside class="indice">
    <details class="indice__caja" open>
      <summary>Categorías</summary>
      <nav aria-label="Categorías">
        <ul class="indice__lista">
          <li><a href="/"<?= $slug === null && !$filtering ? ' aria-current="page"' : '' ?>><span>Todas</span><span class="puntos" aria-hidden="true"></span><span class="cuenta"><?= (int) $catalogTotal ?></span></a></li>
        </ul>
<?php foreach ($families as $family => $categories): ?>
        <h2 class="indice__grupo"><?= $this->e($familyTitles[$family] ?? ucfirst($family)) ?></h2>
        <ul class="indice__lista">
<?php foreach ($categories as $category): ?>
          <li><a href="<?= $this->e($this->url('/', ['categoria' => (string) $category['slug']])) ?>"<?= $slug === $category['slug'] && !$filtering ? ' aria-current="page"' : '' ?>><span><?= $this->e((string) $category['nombre']) ?></span><span class="puntos" aria-hidden="true"></span><span class="cuenta"><?= (int) $category['total'] ?></span></a></li>
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

    <form class="filtro" method="get" action="/" role="search" aria-label="Buscar marcalibros">
<?php if ($slug !== null): ?>
      <input type="hidden" name="categoria" value="<?= $this->e($slug) ?>">
<?php endif ?>
      <p class="filtro__campo">
        <label for="filtro-motivo">Motivo</label>
        <select id="filtro-motivo" name="motivo">
          <option value="">Todos</option>
<?php foreach ($motifOptions as $categoryName => $options): ?>
          <optgroup label="<?= $this->e($categoryName) ?>">
<?php foreach ($options as $value => $name): ?>
            <option value="<?= $this->e($value) ?>"<?= $filters['motivo'] === $value ? ' selected' : '' ?>><?= $this->e($name) ?></option>
<?php endforeach ?>
          </optgroup>
<?php endforeach ?>
        </select>
      </p>
      <p class="filtro__campo">
        <label for="filtro-color">Color</label>
        <select id="filtro-color" name="color">
          <option value="">Todos</option>
<?php foreach ($colorOptions as $value => $name): ?>
          <option value="<?= $this->e($value) ?>"<?= $filters['color'] === $value ? ' selected' : '' ?>><?= $this->e($name) ?></option>
<?php endforeach ?>
        </select>
      </p>
      <p class="filtro__acciones">
        <button class="boton" type="submit">Filtrar<?= $slug !== null ? '<span class="solo-lectores"> en ' . $this->e((string) $current['nombre']) . '</span>' : '' ?></button>
<?php if ($filtering): ?>
        <a href="<?= $this->e($this->url('/', ['categoria' => $slug])) ?>">Quitar filtros</a>
<?php endif ?>
      </p>
    </form>

<?php if ($heading === null): ?>
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
      <h1><?= $this->e($heading) ?></h1>
      <p class="lead"><?= $this->e((string) $lead) ?></p>
    </header>

<?php if ($items !== [] || !$filtering): ?>
    <ul class="rejilla">
<?php if ($page === 1 && !$filtering): ?>
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
<?php endif ?>

<?php if ($pages > 1): ?>
    <nav class="paginas" aria-label="Páginas de <?= $this->e($heading) ?>">
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
