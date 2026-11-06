<?php

declare(strict_types=1);

/**
 * Genera las piezas de cita coloreada para el correo y para el sitio, en salida/:
 *
 *   salida/citas-pos.css                 la hoja de estilo del sitio (colores y marcas de datos/paleta.json)
 *   salida/<origen>/<id>.correo.html     fragmento para el correo: estilos en línea, sin CSS ni imágenes
 *   salida/<origen>/<id>.sitio.html      fragmento para el sitio: clases que usa citas-pos.css
 *   salida/<origen>/<id>.juego.html      fragmento de «Adivina la categoría» (con citas-pos-juego.css y .js)
 *   salida/citas-pos-juego.css y .js     copias de juego/juego.css y juego/juego.js, que se escriben a mano
 *   salida/demo-juego.html               una página autónoma para probar el juego, con todas las frases
 *
 * <origen> es «ejemplos» (las frases de prueba de datos/ejemplos.json, que NO son citas) o «citas»
 * (las de datos/citas.json). Una cita solo se genera si está «verificada», tiene etiquetas y su
 * texto y sus etiquetas tienen una revisión «correcta» o «corregida» en revisiones.csv: este
 * script no publica nada sin revisar.
 *
 *   php citas-pos/bin/render.php              # escribe salida/
 *   php citas-pos/bin/render.php --comprobar  # falla si salida/ no coincide con lo que se generaría
 *
 * Límite: el fragmento del correo no se ha probado en clientes de correo reales. Lleva su propio
 * fondo claro, así que el contraste que mide comprobar-paleta.php vale aunque el cliente use tema
 * oscuro, salvo que el cliente invierta los colores a la fuerza. Si un cliente ignora el estilo del
 * subrayado, los grupos se distinguen solo por el color.
 */

require_once __DIR__ . '/lib/comun.php';

const SALIDA = __DIR__ . '/../salida';
const JUEGO = __DIR__ . '/../juego';
/**
 * @param array<string, mixed> $ejemplos
 * @param array<string, mixed> $citas
 * @param array<string, mixed> $idiomas
 * @param array<string, true> $aprobadas
 * @param list<string> $pendientes ids de citas verificadas a las que aún les falta revisión o etiquetas
 * @return list<array{carpeta: string, id: string, lang: string, texto: string, palabras: list<array{string, string}>, pie: array{string, string}|null}>
 */
function frases(array $ejemplos, array $citas, array $idiomas, array $aprobadas, array &$pendientes): array
{
    $salida = [];
    foreach ($ejemplos['ejemplos'] as $e) {
        foreach ($e['frases'] as $f) {
            $salida[] = ['carpeta' => 'ejemplos', 'id' => $f['id'], 'lang' => $e['html'], 'texto' => $f['texto'], 'palabras' => $f['palabras'], 'pie' => null];
        }
    }
    $dosLetras = array_column($idiomas['idiomas'], 'codigo2', 'codigo');
    foreach ($citas['citas'] as $c) {
        if ($c['estado'] !== 'verificada') {
            continue;
        }
        // Una verificada sin etiquetas tampoco se puede dibujar: se avisa igual que la que espera revisión.
        if (!is_array($c['etiquetas']) || !isset($aprobadas["{$c['idioma']}|cita|{$c['id']}"], $aprobadas["{$c['idioma']}|etiquetas|{$c['id']}"])) {
            $pendientes[] = $c['id'];
            continue;
        }
        $lang = $dosLetras[$c['idioma']] . (isset($c['escritura']) ? '-' . $c['escritura'] : '');
        $salida[] = ['carpeta' => 'citas', 'id' => $c['id'], 'lang' => $lang, 'texto' => $c['texto'], 'palabras' => $c['etiquetas'], 'pie' => [$c['autor'], $c['obra']]];
    }
    return $salida;
}

/** @param array<string, mixed> $paleta */
function hoja(array $paleta): string
{
    $claro = $oscuro = $colores = $marcas = '';
    foreach ($paleta['categorias'] as $c) {
        $claro .= "  --cp-{$c['codigo']}: {$c['claro']};\n";
        $oscuro .= "    --cp-{$c['codigo']}: {$c['oscuro']};\n";
        $colores .= ".citas-pos .w[data-c=\"{$c['codigo']}\"] { --color: var(--cp-{$c['codigo']}); }\n";
    }
    foreach ($paleta['grupos'] as $id => $g) {
        $estilo = ESTILO_DE_MARCA[$g['marca']];
        $marcas .= $estilo === 'none'
            ? ".citas-pos .w[data-g=\"$id\"] { --marca: none; }\n"
            : ".citas-pos .w[data-g=\"$id\"] { --marca: underline $estilo; }\n";
    }
    $fc = $paleta['fondo']['claro'];
    $fo = $paleta['fondo']['oscuro'];
    return <<<CSS
/* Generado por citas-pos/bin/render.php desde datos/paleta.json. No editar a mano. */
.citas-pos {
  --cp-fondo: $fc; --cp-tinta: #1d1d22; --cp-suave: #55555e;
$claro}
@media (prefers-color-scheme: dark) {
  .citas-pos:not([data-tema="claro"]) {
    --cp-fondo: $fo; --cp-tinta: #ececf1; --cp-suave: #a4a4b0;
$oscuro  }
}
.citas-pos[data-tema="oscuro"] {
  --cp-fondo: $fo; --cp-tinta: #ececf1; --cp-suave: #a4a4b0;
$oscuro}
.citas-pos { margin: 0; padding: 16px 20px; border-radius: 8px; background: var(--cp-fondo); color: var(--cp-tinta); }
.citas-pos blockquote { margin: 0; font: 1.375rem/2 Georgia, "Times New Roman", serif; }
.citas-pos blockquote p { margin: 0; }
.citas-pos figcaption { margin-top: 8px; font: 0.8125rem/1.4 system-ui, sans-serif; color: var(--cp-suave); }
.citas-pos .w { color: var(--color); text-decoration: var(--marca, none); text-decoration-thickness: 2px; text-underline-offset: 5px; }
$colores$marcas
CSS;
}

/**
 * @param array<string, array<string, mixed>> $cats
 * @param list<array{string, string|null}> $trozos
 */
function palabras(array $cats, array $trozos, bool $enLinea, array $grupos): string
{
    $html = '';
    foreach ($trozos as [$texto, $cat]) {
        if ($cat === null) {
            $html .= nl2br(h($texto), false);
            continue;
        }
        $c = $cats[$cat] ?? throw new RuntimeException("Categoría desconocida $cat.");
        $titulo = h($cat . ' · ' . $c['nombre']);
        if ($enLinea) {
            $estilo = ESTILO_DE_MARCA[$grupos[$c['grupo']]['marca']];
            $css = "color:{$c['claro']}";
            if ($estilo !== 'none') {
                $css .= ";text-decoration:underline;text-decoration-style:$estilo;text-decoration-thickness:2px;text-underline-offset:5px";
            }
            $html .= "<span style=\"$css\" title=\"$titulo\">" . h($texto) . '</span>';
        } else {
            $html .= '<span class="w" data-c="' . h($cat) . '" data-g="' . h($c['grupo']) . "\" title=\"$titulo\">" . h($texto) . '</span>';
        }
    }
    return $html;
}

/** @param array{string, string}|null $pie */
function pie(?array $pie): string
{
    return $pie === null ? 'Frase de prueba; no es una cita.' : '— ' . h($pie[0]) . ', <i>' . h($pie[1]) . '</i>';
}

/**
 * @param array<string, mixed> $paleta
 * @param array<string, mixed> $f
 */
function correo(array $paleta, array $cats, array $f): string
{
    $fondo = $paleta['fondo']['claro'];
    $cuerpo = palabras($cats, trozos($f['id'], $f['texto'], $f['palabras']), true, $paleta['grupos']);
    $html = "<div lang=\"{$f['lang']}\" style=\"background:$fondo;padding:16px 20px;border-radius:8px;\">\n"
        . "<p style=\"margin:0;font:22px/2 Georgia,'Times New Roman',serif;color:#1d1d22;\">$cuerpo</p>\n"
        . '<p style="margin:8px 0 0;font:13px/1.4 Arial,Helvetica,sans-serif;color:#55555e;">' . pie($f['pie']) . "</p>\n"
        . "</div>\n";
    foreach (['<style', 'class=', 'http', '<script', '<img', 'url('] as $prohibido) {
        if (str_contains($html, $prohibido)) {
            throw new RuntimeException("{$f['id']}: el fragmento del correo no puede llevar «{$prohibido}».");
        }
    }
    return $html;
}

/**
 * @param array<string, mixed> $paleta
 * @param array<string, mixed> $f
 */
function sitio(array $paleta, array $cats, array $f): string
{
    $cuerpo = palabras($cats, trozos($f['id'], $f['texto'], $f['palabras']), false, $paleta['grupos']);
    return "<figure class=\"citas-pos\" lang=\"{$f['lang']}\">\n"
        . "<blockquote><p>$cuerpo</p></blockquote>\n"
        . '<figcaption>' . pie($f['pie']) . "</figcaption>\n"
        . "</figure>\n";
}

/**
 * El fragmento de «Adivina la categoría». Sin JavaScript sale coloreado y con la leyenda completa; con
 * juego/juego.js las palabras empiezan en gris y se revelan al tocarlas, y la leyenda se va llenando.
 *
 * @param array<string, mixed> $paleta
 * @param array<string, array<string, mixed>> $cats
 * @param array<string, mixed> $f
 */
function juego(array $paleta, array $cats, array $f): string
{
    $cuerpo = '';
    $vistas = [];
    foreach (trozos($f['id'], $f['texto'], $f['palabras']) as [$texto, $cat]) {
        if ($cat === null) {
            $cuerpo .= nl2br(h($texto), false);
            continue;
        }
        $c = $cats[$cat] ?? throw new RuntimeException("Categoría desconocida $cat.");
        if ($cat === 'PUNCT') {
            $cuerpo .= '<span class="jp">' . h($texto) . '</span>';
            continue;
        }
        $vistas[$cat] = true;
        $cuerpo .= '<span class="w jw" data-c="' . h($cat) . '" data-g="' . h($c['grupo']) . '" data-nombre="' . h($c['nombre']) . '">' . h($texto) . '</span>';
    }
    $leyenda = '';
    foreach (array_keys($vistas) as $cat) {
        $c = $cats[$cat];
        $leyenda .= '<li data-c="' . h($cat) . '"><span class="w jm" data-c="' . h($cat) . '" data-g="' . h($c['grupo']) . '">Aa</span> ' . h($c['nombre']) . "</li>\n";
    }
    return "<figure class=\"citas-pos citas-pos-juego\" lang=\"{$f['lang']}\">\n"
        . "<p class=\"jayuda\" lang=\"es\" hidden>Toca cada palabra y descubre de qué color es.</p>\n"
        . "<blockquote><p>$cuerpo</p></blockquote>\n"
        . "<ul class=\"jleyenda\" lang=\"es\">\n$leyenda</ul>\n"
        . '<p class="jbar" lang="es" hidden><button type="button" data-accion="todas">Descubrir todas</button> '
        . '<button type="button" data-accion="ocultar">Volver a ocultar</button> '
        . "<span class=\"jestado\" role=\"status\" aria-live=\"polite\"></span></p>\n"
        . '<figcaption>' . pie($f['pie']) . "</figcaption>\n"
        . "</figure>\n";
}

/**
 * Una página de demostración, autónoma (con la hoja y el script dentro), para probar el juego sin montar nada.
 * No es la forma de integrarlo: en un sitio, la hoja y el script van como archivos aparte.
 *
 * @param list<string> $fragmentos
 */
function demo(string $css, string $js, array $fragmentos): string
{
    if (str_contains($js, '</script')) {
        throw new RuntimeException('juego/juego.js no puede contener «</script».');
    }
    $figuras = implode("\n", $fragmentos);
    return <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Adivina la categoría</title>
<!-- Generado por citas-pos/bin/render.php. No editar a mano. -->
<style>
body { margin: 0; padding: 24px 16px 48px; font-family: system-ui, sans-serif; background: #d9d9de; color: #1d1d22; }
@media (prefers-color-scheme: dark) { body { background: #0c0c10; color: #ececf1; } }
main { max-width: 52rem; margin: 0 auto; display: grid; gap: 20px; }
h1 { font-size: 1.4rem; margin: 0; }
.nota { margin: 0; font-size: 0.875rem; opacity: 0.8; }
$css
</style>
</head>
<body>
<main>
<h1>Adivina la categoría</h1>
<p class="nota">Página de prueba. Las frases las escribió quien armó el prototipo y no son citas; sus etiquetas están puestas a mano y ningún hablante nativo las ha revisado.</p>
$figuras
</main>
<script>
$js
</script>
</body>
</html>

HTML;
}

/** @return array<string, string> ruta relativa a SALIDA => contenido */
function generar(array &$pendientes): array
{
    $base = __DIR__ . '/../datos/';
    $paleta = leer($base . 'paleta.json');
    $cats = [];
    foreach ($paleta['categorias'] as $c) {
        $cats[$c['codigo']] = $c;
    }
    $lista = frases(leer($base . 'ejemplos.json'), leer($base . 'citas.json'), leer($base . 'idiomas.json'), aprobadas(__DIR__ . '/../revisiones.csv'), $pendientes);

    $juegoCss = (string) file_get_contents(JUEGO . '/juego.css');
    $juegoJs = (string) file_get_contents(JUEGO . '/juego.js');
    $archivos = [
        'citas-pos.css' => hoja($paleta) . "\n",
        'citas-pos-juego.css' => $juegoCss,
        'citas-pos-juego.js' => $juegoJs,
    ];
    $juegos = [];
    foreach ($lista as $f) {
        $archivos["{$f['carpeta']}/{$f['id']}.correo.html"] = correo($paleta, $cats, $f);
        $archivos["{$f['carpeta']}/{$f['id']}.sitio.html"] = sitio($paleta, $cats, $f);
        $juegos[] = $archivos["{$f['carpeta']}/{$f['id']}.juego.html"] = juego($paleta, $cats, $f);
    }
    $archivos['demo-juego.html'] = demo(hoja($paleta) . "\n" . $juegoCss, $juegoJs, $juegos);
    return $archivos;
}

/** @return list<string> rutas relativas de todo lo que hay en SALIDA */
function existentes(): array
{
    if (!is_dir(SALIDA)) {
        return [];
    }
    $r = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SALIDA, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $r[] = str_replace('\\', '/', substr($f->getPathname(), strlen(SALIDA) + 1));
        }
    }
    sort($r);
    return $r;
}

function main(array $argv): int
{
    $pendientes = [];
    try {
        $archivos = generar($pendientes);
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'ERROR ' . $e->getMessage() . "\n");
        return 1;
    }
    if ($pendientes !== []) {
        echo 'Sin generar, falta su revisión en revisiones.csv: ' . implode(', ', $pendientes) . ".\n";
    }

    if (in_array('--comprobar', $argv, true)) {
        $mal = [];
        foreach ($archivos as $ruta => $contenido) {
            if (!is_file(SALIDA . '/' . $ruta) || file_get_contents(SALIDA . '/' . $ruta) !== $contenido) {
                $mal[] = "$ruta no está al día";
            }
        }
        foreach (array_diff(existentes(), array_keys($archivos)) as $sobra) {
            $mal[] = "$sobra sobra (ya no se genera)";
        }
        foreach ($mal as $m) {
            fwrite(STDERR, "ERROR  $m\n");
        }
        if ($mal !== []) {
            fwrite(STDERR, "Corre: php citas-pos/bin/render.php\n");
            return 1;
        }
        echo 'salida/ está al día (' . count($archivos) . " archivos).\n";
        return 0;
    }

    foreach (array_diff(existentes(), array_keys($archivos)) as $sobra) {
        unlink(SALIDA . '/' . $sobra);
    }
    foreach ($archivos as $ruta => $contenido) {
        $destino = SALIDA . '/' . $ruta;
        if (!is_dir(dirname($destino))) {
            mkdir(dirname($destino), 0775, true);
        }
        file_put_contents($destino, $contenido);
    }
    echo 'Escritos ' . count($archivos) . " archivos en citas-pos/salida/.\n";
    return 0;
}

exit(main($argv));
