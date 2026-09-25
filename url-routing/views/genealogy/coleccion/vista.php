<?php
ob_start();
$activa = 1;
include __DIR__ . '/_cabecera.php';

// Each persona hangs under its father when he is in the tree, otherwise
// under its mother, so nobody with both parents present appears twice.
$enArbol = [];
foreach ($personas as $p) {
    $enArbol[(int)$p['id']] = $p;
}
$hijosDe = [];
$raices = [];
foreach ($enArbol as $id => $p) {
    $ancla = isset($p['padre_id'], $enArbol[(int)$p['padre_id']]) ? (int)$p['padre_id']
        : (isset($p['madre_id'], $enArbol[(int)$p['madre_id']]) ? (int)$p['madre_id'] : null);
    if ($ancla === null) {
        $raices[] = $id;
    } else {
        $hijosDe[$ancla][] = $id;
    }
}

$pintar = static function (array $ids, int $profundidad) use (&$pintar, $enArbol, $hijosDe): void {
    if ($ids === [] || $profundidad > 12) {
        return;
    }
    echo '<ul' . ($profundidad === 0 ? ' class="arbol"' : '') . '>';
    foreach ($ids as $id) {
        $p = $enArbol[$id];
        echo '<li><a href="' . esc(enlace('persona', $id)) . '">' . esc($p['nombres'] . ' ' . $p['apellidos']) . '</a>';
        $pintar($hijosDe[$id] ?? [], $profundidad + 1);
        echo '</li>';
    }
    echo '</ul>';
};
?>
<?php if ($personas === []): ?>
<p class="vacio">La colección está vacía.</p>
<?php else: ?>
<?php $pintar($raices, 0); ?>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Árbol · ' . $coleccion['nombre']; include __DIR__ . '/../_layout.php';
