<?php
/**
 * @var list<array<string, string>> $items
 * @var string $heading
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var string $backUrl
 * @var \Closure(int): string $pageUrl
 */
?>
<div class="envoltura imprimir">
  <div class="imprimir__controles">
    <p class="migas"><a href="<?= $this->e($backUrl) ?>">Volver al catálogo</a></p>
    <h1>Hoja para imprimir</h1>
    <p class="lead"><?= $this->e($heading) ?>: <?= (int) $total ?> marcalibros<?= $pages > 1 ? ', página ' . (int) $page . ' de ' . (int) $pages . ' (' . count($items) . ' en esta hoja)' : '' ?>.</p>
    <p class="ayuda">Salen en tamaño real (18 cm de largo) sobre papel A4, con una guía punteada para cortar. Elige «Guardar como PDF» si quieres un archivo. Si imprimes sin color, activa «Gráficos de fondo» en las opciones de impresión.</p>
    <p class="acciones">
      <button class="boton" type="button" id="imprimir" hidden>Imprimir</button>
<?php if ($page > 1): ?>
      <a class="boton boton--claro" rel="prev" href="<?= $this->e($pageUrl($page - 1)) ?>">Hoja anterior</a>
<?php endif ?>
<?php if ($page < $pages): ?>
      <a class="boton boton--claro" rel="next" href="<?= $this->e($pageUrl($page + 1)) ?>">Hoja siguiente</a>
<?php endif ?>
    </p>
  </div>

  <ul class="hoja">
<?php foreach ($items as $item): ?>
    <li class="pieza">
      <img src="/catalogo/<?= $this->e($item['id']) ?>.svg" width="1400" height="200" alt="<?= $this->e($item['alt']) ?>">
      <span class="pieza__id"><?= $this->e($item['id']) ?></span>
    </li>
<?php endforeach ?>
  </ul>
</div>
