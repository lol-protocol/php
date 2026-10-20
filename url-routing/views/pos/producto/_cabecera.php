<?php /* Expects $producto and $activa (0 = ficha, 1 = variantes, 2 = atributos). */ ?>
<?php
$migas = $producto['grupo_id'] === null ? [] : [[$producto['grupo_nombre'], enlace('grupo', $producto['grupo_id'])]];
$migas[] = [$producto['nombre'], $activa === 0 ? null : enlace('producto', $producto['id'])];
if ($activa !== 0) {
    $migas[] = [[1 => 'Variantes', 2 => 'Atributos'][$activa], null];
}
if ($activa === 0) {
    $hayStock = array_sum(array_map('intval', array_column($variantes ?? [], 'stock'))) > 0;
    $estructurado = [
        '@type' => 'Product',
        'name' => $producto['nombre'],
        'url' => \App\Support\HostParser::origen() . enlace('producto', $producto['id']),
        'offers' => [
            '@type' => 'Offer',
            'price' => number_format($producto['precio_centavos'] / 100, 2, '.', ''),
            'priceCurrency' => $producto['moneda'],
            'availability' => 'https://schema.org/' . (!$producto['activo'] ? 'Discontinued' : ($hayStock ? 'InStock' : 'OutOfStock')),
        ],
    ] + ($producto['descripcion'] !== null ? ['description' => $producto['descripcion']] : []);
}
?>
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
