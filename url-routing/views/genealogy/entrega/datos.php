<?php
/* Expects $contenido (see EntregaRepository::contenido()): everything the site holds about her, in the words of a page. */
$t = static fn(mixed $v): string => esc((string)$v);
$vacio = static fn(): string => '<p class="meta">Ninguno.</p>';
$tabla = static function (array $columnas, array $filas) use ($t): string {
    $html = '<table><thead><tr>';
    foreach ($columnas as $titulo) {
        $html .= '<th scope="col">' . esc($titulo) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($filas as $fila) {
        $html .= '<tr>';
        foreach (array_keys($columnas) as $clave) {
            $valor = $fila[$clave] ?? '';
            $html .= '<td>' . ($valor === true ? 'sí' : ($valor === false ? 'no' : $t($valor))) . '</td>';
        }
        $html .= '</tr>';
    }

    return $html . '</tbody></table>';
};
?>
<?php ob_start(); ?>
<h1>Tus datos</h1>
<p>Esto es lo que el sitio guarda de ti hoy, incluido lo que no se muestra a quien visita. Los nombres de tus familiares no
aparecen: son sus datos, no los tuyos. Si algo no es correcto, o quieres que se borre, responde al correo con el que recibiste
el enlace.</p>

<h2>Tu ficha</h2>
<dl>
<?php foreach ($contenido['ficha'] as $etiqueta => $valor): ?>
    <dt><?= esc($etiqueta) ?></dt><dd><?= $t($valor) ?></dd>
<?php endforeach; ?>
</dl>

<h2>Familiares registrados</h2>
<ul>
    <li>Padre: <?= $contenido['familiares']['padre'] ? 'sí' : 'no' ?></li>
    <li>Madre: <?= $contenido['familiares']['madre'] ? 'sí' : 'no' ?></li>
    <li>Hijos: <?= $t($contenido['familiares']['hijos']) ?></li>
    <li>Otros vínculos (cónyuge, padrinos…): <?= $t($contenido['familiares']['otros_vinculos']) ?></li>
</ul>

<h2>Sucesos en los que apareces</h2>
<?= $contenido['sucesos'] === [] ? $vacio() : $tabla(['tipo' => 'Suceso', 'fecha' => 'Fecha', 'lugar_ruta' => 'Lugar', 'descripcion' => 'Descripción', 'rol' => 'Tu papel'], $contenido['sucesos']) ?>

<h2>Registros que los documentan</h2>
<?= $contenido['registros'] === [] ? $vacio() : $tabla(['titulo' => 'Título', 'tipo' => 'Tipo', 'fecha' => 'Fecha', 'fuente' => 'Fuente'], $contenido['registros']) ?>

<h2>Colecciones</h2>
<?= $contenido['colecciones'] === [] ? $vacio() : $tabla(['nombre' => 'Colección', 'publica' => 'Pública'], $contenido['colecciones']) ?>

<h2>Organizaciones</h2>
<?= $contenido['organizaciones'] === [] ? $vacio() : $tabla(['nombre' => 'Organización', 'rol' => 'Papel', 'desde' => 'Desde', 'hasta' => 'Hasta'], $contenido['organizaciones']) ?>

<h2>Correcciones que se hicieron</h2>
<?= $contenido['correcciones'] === [] ? $vacio() : $tabla(['corregida_en' => 'Cuándo', 'que' => 'Qué dato'], $contenido['correcciones']) ?>

<h2>Pedidos de retiro sobre tu página</h2>
<?= $contenido['pedidos_de_retiro'] === [] ? $vacio() : $tabla(['creado_en' => 'Cuándo', 'motivo' => 'Motivo', 'estado' => 'Estado'], $contenido['pedidos_de_retiro']) ?>

<p class="meta">Este enlace deja de servir pronto o tras unas pocas visitas. Guarda lo que necesites.</p>
<?php $content = ob_get_clean(); $title = 'Tus datos'; $noindex = true; include __DIR__ . '/../_layout.php';
