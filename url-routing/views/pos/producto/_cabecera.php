<?php /* Expects $producto and $activa (0 = ficha, 1 = variantes, 2 = atributos). */ ?>
<?php if ($producto['grupo_id'] !== null): ?>
<nav class="meta" aria-label="Categoría"><a href="<?= esc(enlace('grupo', $producto['grupo_id'])) ?>"><?= esc($producto['grupo_nombre']) ?></a></nav>
<?php endif; ?>
<h1><?= esc($producto['nombre']) ?></h1>
<p class="sub"><?= esc(dinero($producto['precio_centavos'], $producto['moneda'])) ?></p>
<?php if (!$producto['activo']): ?>
<p class="aviso">Este producto está descontinuado y ya no se puede comprar.</p>
<?php endif; ?>
<nav class="acciones" aria-label="Secciones del producto">
    <a href="<?= esc(enlace('producto', $producto['id'])) ?>"<?= $activa === 0 ? ' aria-current="page"' : '' ?>>Ficha</a>
    <a href="<?= esc(accion('producto', $producto['id'], 1)) ?>"<?= $activa === 1 ? ' aria-current="page"' : '' ?>>Variantes</a>
    <a href="<?= esc(accion('producto', $producto['id'], 2)) ?>"<?= $activa === 2 ? ' aria-current="page"' : '' ?>>Atributos</a>
</nav>
