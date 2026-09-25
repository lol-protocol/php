<?php use App\Support\Etiquetas; ob_start(); ?>
<h1><?php if ($atributo['tipo'] === 'color' && $atributo['valor'] !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $atributo['valor']) === 1): ?><span class="muestra" style="background: <?= esc($atributo['valor']) ?>"></span> <?php endif; ?><?= esc($atributo['nombre']) ?></h1>
<p class="sub"><?= esc(Etiquetas::atributo($atributo['tipo'])) ?></p>
<p><a href="<?= esc(accion('atributo', $atributo['id'], 1)) ?>">Ver productos con este <?= esc(mb_strtolower(Etiquetas::atributo($atributo['tipo']))) ?> →</a></p>
<?php $content = ob_get_clean(); $title = $atributo['nombre']; include __DIR__ . '/../_layout.php';
