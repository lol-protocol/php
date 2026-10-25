<?php

declare(strict_types=1);

/**
 * Genera prototipo/index.html (un solo archivo, sin servidor ni conexión) a partir de
 * datos/paleta.json y datos/ejemplos.json.
 *
 *   php citas-pos/bin/prototipo.php              # escribe prototipo/index.html
 *   php citas-pos/bin/prototipo.php --comprobar  # falla si lo guardado no coincide con lo que se generaría
 *
 * Es un prototipo para ver la paleta, no una pieza final: las frases no son citas.
 */

const ESTILO_DE_MARCA = [
    'subrayado' => 'solid',
    'ondulado' => 'wavy',
    'punteado' => 'dotted',
    'discontinuo' => 'dashed',
    'ninguna' => 'none',
];

// Matrices de Machado, Oliveira y Fernandes (2009), severidad 1,0. Las mismas de comprobar-paleta.php.
const DALTONISMO = [
    'protanopia' => ['Protanopía', [[0.152286, 1.052583, -0.204868], [0.114503, 0.786281, 0.099216], [-0.003882, -0.048116, 1.051998]]],
    'deuteranopia' => ['Deuteranopía', [[0.367322, 0.860646, -0.227968], [0.280085, 0.672501, 0.047413], [-0.011820, 0.042940, 0.968881]]],
    'tritanopia' => ['Tritanopía', [[1.255528, -0.076749, -0.178779], [-0.078411, 0.930809, 0.147602], [0.004733, 0.691367, 0.303900]]],
];

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return array<string, mixed> */
function leer(string $ruta): array
{
    return json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Reparte el texto de una frase entre sus palabras etiquetadas, conservando los espacios.
 *
 * @param list<array{string, string}> $palabras
 * @return list<array{string, string|null}> trozos: [texto, categoría o null si es un espacio]
 */
function trozos(string $id, string $texto, array $palabras): array
{
    $salida = [];
    $cursor = 0;
    foreach ($palabras as [$forma, $cat]) {
        $pos = strpos($texto, $forma, $cursor);
        if ($pos === false) {
            throw new RuntimeException("$id: la palabra «{$forma}» no aparece en el texto después de la posición $cursor.");
        }
        $entre = substr($texto, $cursor, $pos - $cursor);
        if (trim($entre) !== '') {
            throw new RuntimeException("$id: hay texto sin etiquetar entre palabras: «{$entre}».");
        }
        if ($entre !== '') {
            $salida[] = [$entre, null];
        }
        $salida[] = [$forma, $cat];
        $cursor = $pos + strlen($forma);
    }
    if (trim(substr($texto, $cursor)) !== '') {
        throw new RuntimeException("$id: queda texto sin etiquetar al final: «" . substr($texto, $cursor) . '».');
    }
    return $salida;
}

function generar(): string
{
    $base = __DIR__ . '/../datos/';
    $paleta = leer($base . 'paleta.json');
    $ejemplos = leer($base . 'ejemplos.json');

    $cats = [];
    foreach ($paleta['categorias'] as $c) {
        $cats[$c['codigo']] = $c;
    }

    $vars = ['claro' => '', 'oscuro' => ''];
    foreach ($paleta['categorias'] as $c) {
        foreach (['claro', 'oscuro'] as $modo) {
            $vars[$modo] .= "    --c-{$c['codigo']}: {$c[$modo]};\n";
        }
    }
    $marcas = '';
    foreach ($paleta['grupos'] as $id => $g) {
        $estilo = ESTILO_DE_MARCA[$g['marca']];
        $marcas .= $estilo === 'none'
            ? "  .w[data-g=\"$id\"] { --marca: none; }\n"
            : "  .w[data-g=\"$id\"] { --marca: underline $estilo; }\n";
    }
    $colores = '';
    foreach (array_keys($cats) as $cod) {
        $colores .= "  .w[data-c=\"$cod\"] { --color: var(--c-$cod); }\n";
    }

    $filtros = '';
    foreach (DALTONISMO as $id => [$nombre, $m]) {
        $v = [];
        foreach ($m as $fila) {
            $v[] = implode(' ', array_map(static fn (float $x): string => (string) $x, $fila)) . ' 0 0';
        }
        $v[] = '0 0 0 1 0';
        $filtros .= "    <filter id=\"f-$id\" color-interpolation-filters=\"linearRGB\"><feColorMatrix type=\"matrix\" values=\"" . implode(' ', $v) . "\"/></filter>\n";
    }

    // Leyenda, por grupos.
    $leyenda = '';
    foreach ($paleta['grupos'] as $idGrupo => $g) {
        $leyenda .= '<section class="grupo"><h3>' . h($g['nombre']) . ' <small>· marca: ' . h($g['marca']) . "</small></h3>\n<ul>\n";
        foreach ($cats as $c) {
            if ($c['grupo'] !== $idGrupo) {
                continue;
            }
            $leyenda .= sprintf(
                "  <li><span class=\"w muestra\" data-c=\"%s\" data-g=\"%s\">Aa</span> <code>%s</code> %s</li>\n",
                h($c['codigo']),
                h($c['grupo']),
                h($c['codigo']),
                h($c['nombre'])
            );
        }
        $leyenda .= "</ul></section>\n";
    }

    // Frases.
    $frases = '';
    foreach ($ejemplos['ejemplos'] as $e) {
        $frases .= '<section class="idioma" lang="' . h($e['html']) . '"><h3 lang="es">' . h($e['nombre']) . ' <small>· <code>' . h($e['idioma']) . "</code></small></h3>\n";
        foreach ($e['frases'] as $f) {
            $frases .= '<p class="frase" id="' . h($f['id']) . '">';
            foreach (trozos($f['id'], $f['texto'], $f['palabras']) as [$texto, $cat]) {
                if ($cat === null) {
                    $frases .= h($texto);
                    continue;
                }
                if (!isset($cats[$cat])) {
                    throw new RuntimeException("{$f['id']}: categoría desconocida $cat.");
                }
                $frases .= sprintf(
                    '<span class="w" data-c="%s" data-g="%s" title="%s · %s">%s</span>',
                    h($cat),
                    h($cats[$cat]['grupo']),
                    h($cat),
                    h($cats[$cat]['nombre']),
                    h($texto)
                );
            }
            $frases .= "</p>\n";
        }
        $frases .= "</section>\n";
    }

    $fondoClaro = $paleta['fondo']['claro'];
    $fondoOscuro = $paleta['fondo']['oscuro'];
    $aviso = h($ejemplos['aviso']);

    return <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Prototipo de la paleta</title>
<!-- Generado por citas-pos/bin/prototipo.php desde datos/paleta.json y datos/ejemplos.json. No editar a mano. -->
<style>
  :root {
    --fondo: $fondoClaro; --tinta: #1d1d22; --suave: #55555e; --borde: #d9d6cf; --panel: #f3f1ec;
{$vars['claro']}  }
  @media (prefers-color-scheme: dark) {
    :root:not([data-tema="claro"]) {
      --fondo: $fondoOscuro; --tinta: #ececf1; --suave: #a4a4b0; --borde: #34353f; --panel: #1d1e25;
{$vars['oscuro']}    }
  }
  :root[data-tema="oscuro"] {
    --fondo: $fondoOscuro; --tinta: #ececf1; --suave: #a4a4b0; --borde: #34353f; --panel: #1d1e25;
{$vars['oscuro']}  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--fondo); color: var(--tinta); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
  main { max-width: 52rem; margin: 0 auto; padding: 24px 16px 64px; }
  h1 { font-size: 1.5rem; margin: 0 0 4px; }
  h2 { font-size: 1.15rem; margin: 32px 0 8px; }
  h3 { font-size: 1rem; margin: 16px 0 6px; }
  small { color: var(--suave); font-weight: 400; }
  code { font: 0.85em ui-monospace, SFMono-Regular, Menlo, monospace; }
  .aviso { background: var(--panel); border: 1px solid var(--borde); border-radius: 8px; padding: 10px 14px; color: var(--suave); }
  .controles { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 10px; margin: 16px 0; }
  .controles label { display: grid; gap: 4px; font-size: 0.85rem; color: var(--suave); }
  .controles select { font: inherit; padding: 6px 8px; color: var(--tinta); background: var(--panel); border: 1px solid var(--borde); border-radius: 6px; }
  .w { color: var(--color); text-decoration: var(--marca, none); text-decoration-thickness: 2px; text-underline-offset: 5px; }
{$colores}{$marcas}  [data-marcas="color"] .w { text-decoration: none; }
  [data-marcas="marca"] .w { color: var(--tinta); }
  [data-etiquetas="si"] .w:not(.muestra):not([data-c="PUNCT"])::after { content: attr(data-c); display: inline-block; font: 600 0.5em ui-monospace, Menlo, monospace; letter-spacing: 0.03em; margin-left: 0.2em; vertical-align: sub; color: var(--suave); }
  .frase { font-size: 1.5rem; line-height: 2.1; margin: 4px 0 10px; }
  .grupo ul { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: 4px 16px; }
  .grupo li { font-size: 0.95rem; }
  .muestra { font-size: 1.25rem; font-weight: 700; display: inline-block; min-width: 1.8rem; }
  [data-vision="protanopia"] #demo { filter: url(#f-protanopia); }
  [data-vision="deuteranopia"] #demo { filter: url(#f-deuteranopia); }
  [data-vision="tritanopia"] #demo { filter: url(#f-tritanopia); }
  footer { margin-top: 32px; color: var(--suave); font-size: 0.85rem; }
</style>
</head>
<body data-vision="normal" data-marcas="ambas" data-etiquetas="no">
<svg width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute">
  <defs>
{$filtros}  </defs>
</svg>
<main>
  <h1>Prototipo de la paleta</h1>
  <p class="aviso">$aviso</p>

  <div class="controles">
    <label>Tema
      <select id="tema"><option value="auto">Según el sistema</option><option value="claro">Claro</option><option value="oscuro">Oscuro</option></select>
    </label>
    <label>Visión
      <select id="vision"><option value="normal">Normal</option><option value="protanopia">Protanopía</option><option value="deuteranopia">Deuteranopía</option><option value="tritanopia">Tritanopía</option></select>
    </label>
    <label>Cómo se distinguen
      <select id="marcas"><option value="ambas">Color y marca</option><option value="color">Solo color</option><option value="marca">Solo marca</option></select>
    </label>
    <label>Categoría junto a cada palabra
      <select id="etiquetas"><option value="no">No</option><option value="si">Sí</option></select>
    </label>
  </div>

  <div id="demo">
    <h2>Leyenda</h2>
    $leyenda
    <h2>Frases de prueba</h2>
    $frases
  </div>

  <footer>
    La «marca» es el estilo del subrayado, que cambia por grupo: así una persona que no distingue ciertos colores aún separa los grupos. Dentro de un grupo, hay pares que algunas visiones confunden; ahí ayuda activar la categoría junto a cada palabra. La simulación de daltonismo es aproximada y no sustituye a probar con personas.
  </footer>
</main>
<script>
  (function () {
    var raiz = document.documentElement, cuerpo = document.body;
    function enlazar(id, aplicar) {
      var el = document.getElementById(id);
      el.addEventListener('change', function () { aplicar(el.value); });
    }
    enlazar('tema', function (v) { if (v === 'auto') { raiz.removeAttribute('data-tema'); } else { raiz.setAttribute('data-tema', v); } });
    enlazar('vision', function (v) { cuerpo.setAttribute('data-vision', v); });
    enlazar('marcas', function (v) { cuerpo.setAttribute('data-marcas', v); });
    enlazar('etiquetas', function (v) { cuerpo.setAttribute('data-etiquetas', v); });
  })();
</script>
</body>
</html>

HTML;
}

function main(array $argv): int
{
    $destino = __DIR__ . '/../prototipo/index.html';
    try {
        $html = generar();
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'ERROR ' . $e->getMessage() . "\n");
        return 1;
    }
    if (in_array('--comprobar', $argv, true)) {
        if (!is_file($destino) || file_get_contents($destino) !== $html) {
            fwrite(STDERR, "prototipo/index.html no está al día: corre php citas-pos/bin/prototipo.php\n");
            return 1;
        }
        echo "prototipo/index.html está al día.\n";
        return 0;
    }
    if (!is_dir(dirname($destino))) {
        mkdir(dirname($destino), 0775, true);
    }
    file_put_contents($destino, $html);
    echo 'Escrito ' . realpath($destino) . ' (' . strlen($html) . " bytes)\n";
    return 0;
}

exit(main($argv));
